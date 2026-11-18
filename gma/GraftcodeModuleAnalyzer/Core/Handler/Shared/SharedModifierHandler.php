<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Utils\AnalyzedObjectUtils;
use GraftcodeModuleAnalyzer\Utils\Exception\GetModifierTypeFromObjectTypeException;
use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeProtocol\{
    Enums\ModifierType,
    Models\UnifiedGraftModel
};
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Exception;

final class SharedModifierHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): int {
        $objectType = AnalyzedObjectUtils::getClassNameOrType($analyzedObject);
        try {
            $this->logger->trackVerbose('Generating modifier for type: {type}', ['type' => $objectType]);

            return match (true) {
                $analyzedObject instanceof ReflectionMethod => $this->generateMethodModifier($analyzedObject),
                $analyzedObject instanceof ReflectionProperty => $this->generateFieldModifier($analyzedObject),
                $analyzedObject instanceof ReflectionClassConstant => $this->generateConstantModifier($analyzedObject),
                $analyzedObject instanceof ReflectionClass => $this->generateTypeModifier($analyzedObject),
                default => throw new GetModifierTypeFromObjectTypeException($analyzedObject)
            };
        } catch (Exception $e) {
            $this->logger->trackError('Error generating modifier for: {analyzedObject}', [
                'analyzedObject' => $objectType,
                'exception' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function generateTypeModifier(ReflectionClass $reflectionClass): int
    {
        $this->logger->trackVerbose('Determining type modifier for ReflectionClass: {className}', [
            'className' => $reflectionClass->getName()
        ]);

        return ModifierType::PUBLIC->value;
    }

    private function generateFieldModifier(ReflectionProperty $reflectionProperty): int
    {
        $modifiers = $reflectionProperty->getModifiers();
        $this->logger->trackVerbose('Determining field modifier for ReflectionProperty: {propertyName}, modifiers: {modifiers}', [
            'propertyName' => $reflectionProperty->getName(),
            'modifiers' => $modifiers
        ]);

        return match (true) {
            $reflectionProperty->isProtected() => ModifierType::PROTECTED->value,
            $reflectionProperty->isPrivate() => ModifierType::PRIVATE->value,
            default => ModifierType::PUBLIC->value
        };
    }

    private function generateConstantModifier(ReflectionClassConstant $constant): int
    {
        return match (true) {
            $constant->isProtected() => ModifierType::PROTECTED->value,
            $constant->isPrivate() => ModifierType::PRIVATE->value,
            default => ModifierType::PUBLIC->value
        };
    }

    private function generateMethodModifier(ReflectionMethod $reflectionMethod): int
    {
        $modifiers = $reflectionMethod->getModifiers();
        $this->logger->trackVerbose('Determining method modifier for ReflectionMethod: {methodName}, modifiers: {modifiers}', [
            'methodName' => $reflectionMethod->getName(),
            'modifiers' => $modifiers
        ]);

        return match (true) {
            $reflectionMethod->isPrivate() => ModifierType::PRIVATE->value,
            $reflectionMethod->isProtected() => ModifierType::PROTECTED->value,
            default => ModifierType::PUBLIC->value
        };
    }
}
