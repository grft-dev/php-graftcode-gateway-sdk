<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedCommentDocHandler;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedMethodCompletionAnalyzerHandler;
use GraftcodeModuleAnalyzer\Utils\Exception\InvokeInstanceMethodAnalyzerHandlerException;
use GraftcodeModuleAnalyzer\Utils\TypeNameResolver;
use GraftcodeModuleAnalyzer\Utils\Const\{
    SpecialChars,
    Types
};
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use ReflectionMethod;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use Throwable;

class InvokeInstanceMethodAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    protected function getMethodType(ReflectionMethod $analyzedMethod): UnifiedGraftModelType
    {
        return UnifiedGraftModelType::INSTANCE_METHOD;
    }

    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        if (!$analyzedObject instanceof ReflectionMethod) {
            throw new InvokeInstanceMethodAnalyzerHandlerException(
                new Exception('Expected ReflectionMethod, got ' . get_class($analyzedObject))
            );
        }

        $analyzedMethod = $analyzedObject;

        $this->logger->trackVerbose(
            'Generating UnifiedGraftModel for instance method: {methodName}',
            ['methodName' => $analyzedMethod->getName()]
        );

        try {
            $handlers->CURRENT_DECLARING_TYPE_FQCN = $analyzedMethod->getDeclaringClass()->getName();
            $handlers->CURRENT_REFLECTION_METHOD = $analyzedMethod;
            $handlers->CURRENT_REFLECTION_PARAMETER_NAME = '';

            $methodType = $this->getMethodType($analyzedMethod);

            $invokeInstanceMethodCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                $methodType
            );

            // Build the complete method definition using PHP 8.4 sequential processing
            $invokeInstanceMethodCommand = $this->addMethodNameToPayload($invokeInstanceMethodCommand, $analyzedMethod);
            $invokeInstanceMethodCommand = $this->addReturnTypeToPayload($invokeInstanceMethodCommand, $analyzedMethod, $handlers);
            $invokeInstanceMethodCommand = $this->addModifiersToPayload($invokeInstanceMethodCommand, $analyzedMethod, $handlers);
            $invokeInstanceMethodCommand = $this->addParametersToPayload($invokeInstanceMethodCommand, $analyzedMethod, $handlers);
            $invokeInstanceMethodCommand = $this->addAttributesToPayload($invokeInstanceMethodCommand, $analyzedMethod);
            $invokeInstanceMethodCommand = SharedMethodCompletionAnalyzerHandler::appendToPayloadIfNeeded(
                $analyzedMethod,
                $invokeInstanceMethodCommand
            );

            $invokeInstanceMethodCommand = SharedCommentDocHandler::appendToPayloadIfPresent(
                $analyzedMethod,
                $invokeInstanceMethodCommand,
                $handlers
            );

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for instance method: {methodName}',
                ['methodName' => $analyzedMethod->getName()]
            );

            return $invokeInstanceMethodCommand;

        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for instance method',
                'methodName' => $analyzedMethod->getName()
            ]);

            throw new InvokeInstanceMethodAnalyzerHandlerException($e);
        }
    }

    private function addMethodNameToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method
    ): UnifiedGraftModel {
        $this->logger->trackVerbose(
            'Adding method name to payload: {methodName}',
            ['methodName' => $method->getName()]
        );

        return $command->addArgToPayload($method->getName());
    }

    /**
     * @throws Exception
     */
    private function addReturnTypeToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $returnType = $method->getReturnType();
        $returnTypeName = $this->extractTypeName($returnType, $method);

        $this->logger->trackVerbose(
            'Adding return type to payload: {returnType} for method: {methodName}',
            [
                'returnType' => $returnTypeName,
                'methodName' => $method->getName()
            ]
        );

        $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value] 
            ?? throw new Exception('TYPE_USAGE handler not available');

        /** @var UnifiedGraftModel $typeResult */
        $typeResult = $typeHandler->generateUnifiedGraftModel(
            $returnType ?? (object)['type' => Types::MIXED],
            $command,
            $handlers
        );

        return $command->addArgToPayload($typeResult);
    }

    private function extractTypeName(
        ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type,
        ReflectionMethod $method
    ): string {
        return match (true) {
            $type === null => Types::MIXED,
            $type instanceof ReflectionNamedType => $this->resolveNamedType($type, $method),
            $type instanceof ReflectionUnionType => implode(SpecialChars::OR,
                array_map(fn(ReflectionNamedType $t) => $this->resolveNamedType($t, $method), $type->getTypes())
            ),
            $type instanceof ReflectionIntersectionType => implode(SpecialChars::END,
                array_map(fn(ReflectionNamedType $t) => $this->resolveNamedType($t, $method), $type->getTypes())
            ),
            default => $type->__toString()
        };
    }

    private function resolveNamedType(ReflectionNamedType $type, ReflectionMethod $method): string
    {
        $typeName = $type->getName();

        if ($typeName === Types::SELF || $typeName === Types::STATIC) {
            return $method->getDeclaringClass()->getShortName();
        }

        if ($typeName === Types::PARENT) {
            $parentClass = $method->getDeclaringClass()->getParentClass();

            return $parentClass ? $parentClass->getShortName() : Types::PARENT;
        }

        if (str_contains($typeName, '\\')) {
            return TypeNameResolver::getShortNameFromType($typeName);
        }

        return $typeName;
    }

    /**
     * @throws Exception
     */
    private function addModifiersToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $modifiers = $method->getModifiers();

        $this->logger->trackVerbose(
            'Adding modifiers to payload for method: {methodName}, Modifiers: {modifiers}',
            [
                'methodName' => $method->getName(),
                'modifiers' => $modifiers
            ]
        );

        $modifierHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODIFIER->value]
            ?? throw new Exception('MODIFIER handler not available');

        /** @var int $modifierResult */
        $modifierResult = $modifierHandler->generateUnifiedGraftModel(
            $method,
            $command,
            $handlers
        );

        return $command->addArgToPayload($modifierResult);
    }

    /**
     * @throws Exception
     */
    private function addParametersToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $this->logger->trackVerbose(
            'Adding parameters array to payload for method: {methodName}',
            ['methodName' => $method->getName()]
        );

        $parametersHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::PARAMETERS_ARRAY->value]
            ?? throw new Exception('PARAMETERS_ARRAY handler not available');

        /** @var UnifiedGraftModel $parametersResult */
        $parametersResult = $parametersHandler->generateUnifiedGraftModel(
            $method,
            $command,
            $handlers
        );

        return $command->addArgToPayload($parametersResult);
    }

    private function addAttributesToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method
    ): UnifiedGraftModel {
        $attributes = $method->getAttributes();

        if (empty($attributes)) {
            return $command;
        }

        $this->logger->trackVerbose(
            'Adding {count} attributes to payload for method: {methodName}',
            [
                'count' => count($attributes),
                'methodName' => $method->getName()
            ]
        );

        foreach ($attributes as $attribute) {
            $attributeData = $this->processAttribute($attribute);

            // Add attribute as metadata to the command
            $command = $command->addArgToPayload([
                'attribute_name' => $attributeData['name'],
                'attribute_arguments' => $attributeData['arguments'],
                'attribute_target' => 'method'
            ]);
        }

        return $command;
    }

    private function processAttribute(ReflectionAttribute $attribute): array
    {
        try {
            return [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments()
            ];
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error processing attribute',
                'attributeName' => $attribute->getName()
            ]);

            return [
                'name' => $attribute->getName(),
                'arguments' => []
            ];
        }
    }
}
