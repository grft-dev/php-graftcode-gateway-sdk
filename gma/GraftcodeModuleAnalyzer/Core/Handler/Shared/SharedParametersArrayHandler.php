<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    AnalyzedObjectUtils,
    Const\SpecialChars,
    Const\Types,
    Exception\ExpectedReflectionTypeException,
    TypeNameResolver
};
use GraftcodeProtocol\{
    Enums\IsNullable,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

final class SharedParametersArrayHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Throwable
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->logger->trackVerbose(
                sprintf('Generating parameters array for method: {%s}', get_class($parentCommand)),
                ['method' => get_class($parentCommand)]
            );

            $parameterArray = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::PARAMETERS_ARRAY
            );

            if (!($analyzedObject instanceof ReflectionFunction) && !($analyzedObject instanceof ReflectionMethod)) {
                throw new ExpectedReflectionTypeException($analyzedObject);
            }

            $parameterArray = $this->addParametersToParameterArray(
                $parameterArray,
                $analyzedObject,
                $handlers,
                $parentCommand
            );

            $this->logger->trackVerbose('Parameters array generated successfully');

            return $parameterArray;

        } catch (Throwable $e) {
            $this->logger->trackException($e,[
                'message' => sprintf(
                    'Error generating parameters array for: {%s} ',
                    AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
                )
            ]);
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addParametersToParameterArray(
        UnifiedGraftModel $parameterArray,
        ReflectionFunction|ReflectionMethod $reflection,
        Handlers $handlers,
        UnifiedGraftModel $parentCommand
    ): UnifiedGraftModel {
        $parameters = $reflection->getParameters();
        $parameterCount = count($parameters);
        $methodName = $this->getMethodName($reflection);

        $this->logger->trackVerbose(
            sprintf('Processing {%s} parameters for method: {%s}', $parameterCount, $methodName),
            [
                'count' => $parameterCount,
                'methodName' => $methodName
            ]
        );

        foreach ($parameters as $index => $parameter) {
            try {
                $parameterName = $parameter->getName();
                $parameterType = $this->getParameterTypeName($parameter);
                $handlers->CURRENT_REFLECTION_PARAMETER_NAME = $parameterName;

                $this->logger->trackVerbose(
                    sprintf('Processing parameter {%s}: {%s} of type {%s}', $index, $parameterName, $parameterType),
                    [
                        'index' => $index,
                        'parameterName' => $parameterName,
                        'parameterType' => $parameterType
                    ]
                );

                $parameterCommand = $this->createParameterCommand($parameter, $handlers, $parentCommand);
                $parameterArray = $parameterArray->addArgToPayload($parameterCommand);

                $this->logger->trackVerbose('Parameter {Index} processed successfully', ['index' => $index]);

            } catch (Throwable $e) {
                $this->logger->trackException($e, [
                    sprintf('Error processing parameter at index {%d}', $index)
                ]);

                throw $e;
            }
        }

        return $parameterArray;
    }

    /**
     * @throws Throwable
     */
    private function createParameterCommand(
        ReflectionParameter $parameter,
        Handlers $handlers,
        UnifiedGraftModel $parentCommand
    ): UnifiedGraftModel {
        $parameterCommand = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::PARAMETER
        );

        $parameterCommand = $this->addTypeUsageToParameter(
            $parameterCommand,
            $parameter,
            $handlers,
            $parentCommand
        );

        $parameterCommand = $parameterCommand->addArgToPayload($parameter->getName());

        return $this->addIsParameterOptional($parameterCommand, $parameter);
    }

    private function addIsParameterOptional(
        UnifiedGraftModel $parameterCommand,
        ReflectionParameter $parameter
    ): UnifiedGraftModel {
        if (!$parameter->isOptional()) {
            return $parameterCommand->addArgToPayload(0);
        }

        $parameterCommand = $parameterCommand->addArgToPayload(1);
        $defaultValue = $this->normalizeDefaultValue($parameter);
        if ($defaultValue === null && !$parameter->isDefaultValueAvailable()) {
            return $parameterCommand;
        }

        return $parameterCommand->addArgToPayload($defaultValue);
    }

    private function normalizeDefaultValue(ReflectionParameter $parameter): mixed
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return null;
        }

        try {
            $value = $parameter->getDefaultValue();
        } catch (Throwable) {
            return null;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    /**
     * @throws Throwable
     */
    private function addTypeUsageToParameter(
        UnifiedGraftModel $parameterCommand,
        ReflectionParameter $parameter,
        Handlers $handlers,
        UnifiedGraftModel $parentCommand
    ): UnifiedGraftModel {
        $type = $parameter->getType();
        $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value] ?? null;

        if ($typeHandler instanceof HandlersInterface && $type !== null) {
            $typeUsageModel = $typeHandler->generateUnifiedGraftModel($type, $parentCommand, $handlers);

            return $parameterCommand->addArgToPayload($typeUsageModel);
        }

        // Fallback for untyped parameters
        $typeName = $this->getParameterTypeName($parameter);
        $resolvedTypeName = TypeNameResolver::resolveRelativeTypeName($typeName, $handlers);
        $isNullable = ($type instanceof ReflectionNamedType && $type->allowsNull())
            ? IsNullable::TRUE->value
            : IsNullable::FALSE->value;
        $typeUsageModel = new SharedTypeHandler()->buildPrimitiveTypeUsage(
            $resolvedTypeName,
            $isNullable,
            $handlers
        );

        return $parameterCommand->addArgToPayload($typeUsageModel);
    }

    private function getParameterTypeName(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        return match (true) {
            $type === null => Types::MIXED,
            $type instanceof ReflectionNamedType => $type->getName(),
            $type instanceof ReflectionUnionType => implode(SpecialChars::OR,
                array_map(fn(ReflectionNamedType $t) => $t->getName(), $type->getTypes())
            ),
            $type instanceof ReflectionIntersectionType => implode(SpecialChars::END,
                array_map(fn(ReflectionNamedType $t) => $t->getName(), $type->getTypes())
            ),
            default => Types::UNKNOWN
        };
    }

    private function getMethodName(ReflectionFunction|ReflectionMethod $reflection): string
    {
        return match (true) {
            $reflection instanceof ReflectionMethod =>
                $reflection->getDeclaringClass()->getName() . SpecialChars::DOUBLE_COLON . $reflection->getName(),
            $reflection instanceof ReflectionFunction => $reflection->getName(),
            default => Types::UNKNOWN
        };
    }
}
