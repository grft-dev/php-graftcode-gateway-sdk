<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeModuleAnalyzer\Utils\{
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
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class GetInstanceFieldAnalyzerHandler extends BaseHandler implements HandlersInterface
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

            /** @var ReflectionProperty $instanceField */
            $instanceField = $analyzedObject;
            $fieldName = $instanceField->getName();
            $className = $instanceField->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS;

            $this->logger->trackVerbose(
                'Generating UnifiedGraftModel for instance field: {fieldName} in class: {className}',
                [
                    'fieldName' => $fieldName,
                    'className' => $className
                ]
            );

            $getInstanceFieldCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::INSTANCE_FIELD
            );

            $getInstanceFieldCommand = $this->addFieldNameToPayload(
                $getInstanceFieldCommand,
                $instanceField,
                $handlers
            );

            $getInstanceFieldCommand = $this->addFieldTypeToPayload(
                $getInstanceFieldCommand,
                $instanceField,
                $handlers
            );

            $getInstanceFieldCommand = $this->addSetterToPayload(
                $getInstanceFieldCommand,
                $instanceField,
                $handlers
            );

            $getInstanceFieldCommand = $this->addGetterToPayload(
                $getInstanceFieldCommand,
                $instanceField,
                $handlers
            );

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for instance field: {fieldName}',
                ['fieldName' => $fieldName]
            );

            return $getInstanceFieldCommand;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for instance field',
                'analyzedObject' => get_class($analyzedObject)
            ]);

            throw $e;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateAnalyzedObject(object $analyzedObject): void
    {
        if (!($analyzedObject instanceof ReflectionProperty)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected ReflectionProperty for instance field analysis, got %s',
                    get_class($analyzedObject)
                )
            );
        }
    }

    /**
     * @throws Throwable
     */
    private function addFieldNameToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $fieldName = $field->getName();
            $this->logger->trackVerbose(
                'Adding field name to payload: {fieldName}',
                ['fieldName' => $fieldName]
            );

            /** @var HandlersInterface $methodNameHandler */
            $methodNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::METHOD_NAME->value];

            // Create a temporary object to hold the field name
            $fieldNameObject = new readonly class($fieldName) {
                public function __construct(public string $name) {}
                public function __toString(): string { return $this->name; }
            };

            return $command->addArgToPayload(
                $methodNameHandler->generateUnifiedGraftModel(
                    $fieldNameObject,
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
        ReflectionProperty $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $fieldType = $this->determineFieldType($field);
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

            // Create a temporary object to hold the field type
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
            return Types::MIXED; //Types::NULL
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
        ReflectionProperty $field,
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
        ReflectionProperty $field,
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

    private function resolveFieldModifier(ReflectionProperty $field, Handlers $handlers): int
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
