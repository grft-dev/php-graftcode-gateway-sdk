<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeModuleAnalyzer\Utils\{
    AnalyzedObjectUtils,
    Const\CustomTypes,
    Const\SpecialChars,
    Const\Types
};
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use InvalidArgumentException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class GetStaticFieldAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->validateAnalyzedObject($analyzedObject);

            $fieldName = match (true) {
                $analyzedObject instanceof ReflectionProperty => $analyzedObject->getName(),
                $analyzedObject instanceof ReflectionClassConstant => $analyzedObject->getName(),
                default => CustomTypes::UNKNOWN_CLASS,
            };
            $className = match (true) {
                $analyzedObject instanceof ReflectionProperty => $analyzedObject->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS,
                $analyzedObject instanceof ReflectionClassConstant => $analyzedObject->getDeclaringClass()->getName(),
                default => CustomTypes::UNKNOWN_CLASS,
            };

            $this->logger->trackVerbose(
                'Generating UnifiedGraftModel for static field: {fieldName} in class: {className}',
                [
                    'fieldName' => $fieldName,
                    'className' => $className
                ]
            );

            $getStaticFieldCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::STATIC_FIELD
            );

            $getStaticFieldCommand = $this->addFieldNameToPayload(
                $getStaticFieldCommand,
                $analyzedObject,
                $handlers
            );

            $getStaticFieldCommand = $this->addFieldTypeToPayload(
                $getStaticFieldCommand,
                $analyzedObject,
                $handlers
            );

            $getStaticFieldCommand = $this->addSetterToPayload(
                $getStaticFieldCommand,
                $analyzedObject,
                $handlers
            );

            $getStaticFieldCommand = $this->addGetterToPayload(
                $getStaticFieldCommand,
                $analyzedObject,
                $handlers
            );

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for static field: {fieldName}',
                ['fieldName' => $fieldName]
            );

            return $getStaticFieldCommand;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for static field',
                'analyzedObject' => AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
            ]);

            throw $e;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateAnalyzedObject(mixed $analyzedObject): void
    {
        if ($analyzedObject instanceof ReflectionClassConstant) {
            if (!$analyzedObject->isPublic()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected public ReflectionClassConstant for static field analysis, got non-public constant: %s',
                        $analyzedObject->getName()
                    )
                );
            }

            return;
        }

        if (!($analyzedObject instanceof ReflectionProperty)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected ReflectionProperty for static field analysis, got %s',
                    AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
                )
            );
        }

        if (!$analyzedObject->isStatic()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected static ReflectionProperty for static field analysis, got non-static field: %s',
                    $analyzedObject->getName()
                )
            );
        }
    }

    /**
     * @throws Throwable
     */
    private function addFieldNameToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty|ReflectionClassConstant $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $fieldName = $field->getName();
            $this->logger->trackVerbose(
                'Adding field name to payload: {fieldName}',
                ['fieldName' => $fieldName]
            );

            /** @var HandlersInterface $fieldNameHandler */
            $fieldNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::FIELD_NAME->value];

            return $command->addArgToPayload(
                $fieldNameHandler->generateUnifiedGraftModel(
                    $fieldName,
                    $command,
                    $handlers
                )
            );
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding field name to payload',
                'fieldName' => $field->getName()
            ]);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addFieldTypeToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty|ReflectionClassConstant $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $fieldType = match (true) {
                $field instanceof ReflectionProperty => $this->determineFieldType($field),
                $field instanceof ReflectionClassConstant => $this->determineConstantType($field),
            };
            $fieldName = $field->getName();

            $this->logger->trackVerbose(
                'Adding field type to payload: {fieldType} for field: {fieldName}',
                [
                    'fieldType' => $fieldType,
                    'fieldName' => $fieldName
                ]
            );

            /** @var HandlersInterface $typeHandler */
            $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value];

            // Create a temporary object to hold the field type (following existing PHP pattern)
            $fieldTypeObject = new readonly class($fieldType) {
                public function __construct(public string $type) {}
                public function __toString(): string { return $this->type; }
            };

            return $command->addArgToPayload(
                $typeHandler->generateUnifiedGraftModel(
                    $fieldTypeObject,
                    $command,
                    $handlers
                )
            );
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding field type to payload',
                'fieldName' => $field->getName()
            ]);

            throw $e;
        }
    }

    private function determineFieldType(ReflectionProperty $field): string
    {
        $type = $field->getType();
        
        if ($type === null) {
            return Types::MIXED;
        }

        return match (true) {
            $type instanceof ReflectionNamedType => $this->handleNamedType($type),
            $type instanceof ReflectionUnionType => $this->handleUnionType($type),
            $type instanceof ReflectionIntersectionType => $this->handleIntersectionType($type),
            default => Types::MIXED
        };
    }

    private function handleNamedType(ReflectionNamedType $type): string
    {
        $typeName = $type->getName();

        if (class_exists($typeName)) {
            $reflectionClass = new ReflectionClass($typeName);
            if ($reflectionClass->hasMethod('getGenericArguments')) {
                $typeName = $this->extractGenericTypeName($typeName);
            }
        }

        return $type->allowsNull() && $typeName !== Types::MIXED ? '?'. $typeName : $typeName;
    }

    private function determineConstantType(ReflectionClassConstant $constant): string
    {
        $type = $constant->getType();

        if ($type instanceof ReflectionNamedType) {
            return $this->handleNamedType($type);
        }

        return $this->inferConstantTypeFromValue($constant->getValue());
    }

    private function inferConstantTypeFromValue(mixed $value): string
    {
        return match (true) {
            is_int($value) => Types::INT,
            is_string($value) => Types::STRING,
            is_bool($value) => Types::BOOL,
            is_float($value) => Types::FLOAT,
            is_array($value) => Types::ARRAY,
            default => Types::MIXED,
        };
    }

    private function handleUnionType(ReflectionUnionType $type): string
    {
        $typeNames = array_map(
            fn(ReflectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : Types::MIXED,
            $type->getTypes()
        );

        return implode(SpecialChars::OR, $typeNames);
    }

    private function handleIntersectionType(ReflectionIntersectionType $type): string
    {
        $typeNames = array_map(
            fn(ReflectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : Types::MIXED,
            $type->getTypes()
        );

        return implode(SpecialChars::END, $typeNames);
    }

    private function extractGenericTypeName(string $typeName): string
    {
        if (preg_match('/^(.+)<(.+)>$/', $typeName, $matches)) {
            return $matches[2]; // Return the generic argument type
        }

        return $typeName;
    }

    /**
     * @throws Throwable
     */
    private function addSetterToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty|ReflectionClassConstant $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $modifiers = $field->getModifiers();
            $fieldName = $field->getName();

            $this->logger->trackVerbose(
                'Adding field modifiers to payload for field: {fieldName}, Modifiers: {modifiers}',
                [
                    'fieldName' => $fieldName,
                    'modifiers' => $modifiers
                ]
            );

            $setter = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::SETTER,
                [$this->resolveFieldModifier($field, $handlers)]
            );

            return $command->addArgToPayload($setter);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding field modifiers to payload',
                'fieldName' => $field->getName()
            ]);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addGetterToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty|ReflectionClassConstant $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->logger->trackVerbose('Adding getter metadata to payload');
            $getter = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::GETTER,
                [$this->resolveFieldModifier($field, $handlers)]
            );
            return $command->addArgToPayload($getter);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding getter metadata to payload'
            ]);

            throw $e;
        }
    }

    private function resolveFieldModifier(ReflectionProperty|ReflectionClassConstant $field, Handlers $handlers): int
    {
        /** @var HandlersInterface $modifierHandler */
        $modifierHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODIFIER->value];

        return $modifierHandler->generateUnifiedGraftModel(
            $field,
            new UnifiedGraftModel(RuntimeName::PHP, UnifiedGraftModelType::MODIFIER),
            $handlers
        );
    }
}
