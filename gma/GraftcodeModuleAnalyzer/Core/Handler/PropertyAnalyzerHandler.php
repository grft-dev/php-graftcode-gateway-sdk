<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeModuleAnalyzer\Utils\Const\CustomTypes;
use GraftcodeProtocol\{
    Enums\ModifierType,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use InvalidArgumentException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class PropertyAnalyzerHandler extends BaseHandler implements HandlersInterface
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

            /** @var ReflectionProperty $property */
            $property = $analyzedObject;
            $propertyName = $property->getName();
            $className = $property->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS;

            $this->logger->trackVerbose(
                'Generating UnifiedGraftModel for property: {propertyName} in class: {className}',
                [
                    'propertyName' => $propertyName,
                    'className' => $className
                ]
            );

            $propertyCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::PROPERTY
            );

            // PROPERTY payload structure: STRING:PROPERTY_NAME, TYPE_USAGE, SETTER, GETTER
            $propertyCommand = $this->addPropertyNameToPayload($propertyCommand, $property, $handlers);
            $propertyCommand = $this->addPropertyTypeToPayload($propertyCommand, $property, $handlers);
            $propertyCommand = $this->addSetterToPayload($propertyCommand, $property, $handlers);
            $propertyCommand = $this->addGetterToPayload($propertyCommand, $property, $handlers);

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for property: {propertyName}',
                ['propertyName' => $propertyName]
            );

            return $propertyCommand;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for property',
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
                    'Expected ReflectionProperty for property analysis, got %s',
                    get_class($analyzedObject)
                )
            );
        }
    }

    /**
     * @throws Throwable
     */
    private function addPropertyNameToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty $property,
        Handlers $handlers //@TODO Check why param $handlers is not use
    ): UnifiedGraftModel {
        try {
            $propertyName = $property->getName();
            $this->logger->trackVerbose(
                'Adding property name to payload: {propertyName}',
                ['propertyName' => $propertyName]
            );

            return $command->addArgToPayload($propertyName);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding property name to payload',
                'propertyName' => $property->getName()
            ]);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addPropertyTypeToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty $property,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $propertyType = $this->determinePropertyType($property);
            $propertyName = $property->getName();

            $this->logger->trackVerbose(
                'Adding property type to payload: {propertyType} for property: {propertyName}',
                [
                    'propertyType' => $propertyType,
                    'propertyName' => $propertyName
                ]
            );

            /** @var HandlersInterface $typeHandler */
            $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value];

            // Create a temporary object to hold the property type
            $propertyTypeObject = new readonly class($propertyType) {
                public function __construct(public string $type) {}
                public function __toString(): string { return $this->type; }
            };

            return $command->addArgToPayload(
                $typeHandler->generateUnifiedGraftModel(
                    $propertyTypeObject,
                    $command,
                    $handlers
                )
            );
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding property type to payload',
                'propertyName' => $property->getName()
            ]);

            throw $e;
        }
    }

    private function determinePropertyType(ReflectionProperty $property): string
    {
        $type = $property->getType();
        
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
        return $type->allowsNull() && $typeName !== Types::MIXED ? '?' . $typeName : $typeName;
    }

    private function handleUnionType(ReflectionUnionType $type): string
    {
        $typeNames = array_map(
            fn(ReflectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : Types::MIXED,
            $type->getTypes()
        );

        return implode('|', $typeNames);
    }

    private function handleIntersectionType(ReflectionIntersectionType $type): string
    {
        $typeNames = array_map(
            fn(ReflectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : Types::MIXED,
            $type->getTypes()
        );

        return implode('&', $typeNames);
    }

    /**
     * @throws Throwable
     */
    private function addSetterToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty $property,
        Handlers $handlers //@TODO Check why param $handlers is not use
    ): UnifiedGraftModel {
        try {
            $setterModifier = $this->getSetterModifier($property);
            $propertyName = $property->getName();
            // READONLY_SETTER: PHP readonly property; PACKAGE_PRIVATE modifier suppresses GCG setter generation.
            $setterType = $property->isReadOnly()
                ? UnifiedGraftModelType::READONLY_SETTER
                : UnifiedGraftModelType::SETTER;

            $this->logger->trackVerbose(
                'Adding setter to payload for property: {propertyName}, modifier: {modifier}, type: {setterType}',
                [
                    'propertyName' => $propertyName,
                    'modifier' => $setterModifier,
                    'setterType' => $setterType->name,
                ]
            );

            $setter = new UnifiedGraftModel(
                RuntimeName::PHP,
                $setterType,
                [$setterModifier]
            );

            return $command->addArgToPayload($setter);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding setter to payload',
                'propertyName' => $property->getName()
            ]);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addGetterToPayload(
        UnifiedGraftModel $command,
        ReflectionProperty $property,
        Handlers $handlers //@TODO Check why param $handlers is not use
    ): UnifiedGraftModel {
        try {
            $getterModifier = $this->getGetterModifier($property);
            $propertyName = $property->getName();

            $this->logger->trackVerbose(
                'Adding getter to payload for property: {propertyName}, modifier: {modifier}',
                [
                    'propertyName' => $propertyName,
                    'modifier' => $getterModifier
                ]
            );

            // GETTER payload: ENUM:MODIFIER
            $getter = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::GETTER,
                [$getterModifier]
            );

            return $command->addArgToPayload($getter);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding getter to payload',
                'propertyName' => $property->getName()
            ]);

            throw $e;
        }
    }

    /**
     * Determines the setter modifier based on property visibility and readonly status.
     */
    private function getSetterModifier(ReflectionProperty $property): int
    {
        // Check if property is readonly (PHP 8.1+)
        if ($property->isReadOnly()) {
            return ModifierType::PACKAGE_PRIVATE->value;
        }

        // Check for explicit setter method
        $declaringClass = $property->getDeclaringClass();
        $propertyName = $property->getName();
        $setterName = 'set' . ucfirst($propertyName);

        if ($declaringClass->hasMethod($setterName)) {
            $setterMethod = $declaringClass->getMethod($setterName);
            if ($setterMethod->isPublic()) {
                return ModifierType::PUBLIC->value;
            }
            if ($setterMethod->isProtected()) {
                return ModifierType::PROTECTED->value;
            }
            if ($setterMethod->isPrivate()) {
                return ModifierType::PRIVATE->value;
            }
        }

        // Default based on property visibility
        if ($property->isPublic()) {
            return ModifierType::PUBLIC->value;
        }
        if ($property->isProtected()) {
            return ModifierType::PROTECTED->value;
        }
        if ($property->isPrivate()) {
            return ModifierType::PRIVATE->value;
        }

        return ModifierType::PUBLIC->value;
    }

    /**
     * Determines the getter modifier based on property visibility.
     */
    private function getGetterModifier(ReflectionProperty $property): int
    {
        // Check for explicit getter method
        $declaringClass = $property->getDeclaringClass();
        $propertyName = $property->getName();
        $getterName = 'get' . ucfirst($propertyName);

        if ($declaringClass->hasMethod($getterName)) {
            $getterMethod = $declaringClass->getMethod($getterName);
            if ($getterMethod->isPublic()) {
                return ModifierType::PUBLIC->value;
            }
            if ($getterMethod->isProtected()) {
                return ModifierType::PROTECTED->value;
            }
            if ($getterMethod->isPrivate()) {
                return ModifierType::PRIVATE->value;
            }
        }

        // Default based on property visibility
        if ($property->isPublic()) {
            return ModifierType::PUBLIC->value;
        }
        if ($property->isProtected()) {
            return ModifierType::PROTECTED->value;
        }
        if ($property->isPrivate()) {
            return ModifierType::PRIVATE->value;
        }

        return ModifierType::PUBLIC->value;
    }
}
