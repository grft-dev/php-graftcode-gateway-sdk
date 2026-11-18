<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use Exception;
use GraftcodeModuleAnalyzer\Utils\Const\Chars;
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use GraftcodeModuleAnalyzer\Core\Handler\{
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\TypeNameResolver;
use GraftcodeProtocol\{
    Enums\IsNullable,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};

/**
 * Handler for array types.
 * Produces nested structure: TYPE_USAGE -> TYPE_USAGE_ARRAY
 * TYPE_USAGE_ARRAY payload: [IS_NULLABLE, ARRAY_DIMENSION, TYPE_USAGE_NODE]
 */
final class SharedArrayTypeHandler extends SharedTypeHandler
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            // TYPE_USAGE wraps TYPE_USAGE_ARRAY per specification
            $typeUsageWrapper = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE
            );

            $typeName = $this->extractTypeName($analyzedObject);
            $isNullable = $this->getIsNullable($analyzedObject, $typeName);
            
            // Calculate array dimension (count of [] in type name)
            $arrayDimension = $this->calculateArrayDimension($typeName);
            
            // Extract element type (remove [] brackets or read from PHPDoc)
            $elementTypeName = $this->resolveElementType($typeName, $handlers);

            $elementTypeNode = $this->generateElementTypeUsageNode(
                $elementTypeName,
                $parentCommand,
                $handlers
            );

            // If no handler found, create a default primitive type node
            if (null === $elementTypeNode) {
                $resolvedElementType = TypeNameResolver::resolveRelativeTypeName($elementTypeName, $handlers);
                $elementTypeNode = $this->buildPrimitiveTypeUsage(
                    $resolvedElementType,
                    IsNullable::FALSE->value,
                    $handlers
                )->getPayload()[0];
            }

            // TYPE_USAGE_ARRAY payload: ENUM:IS_NULLABLE, INT:ARRAY_DIMENSION, <TYPE_USAGE_NODE>
            $arrayTypeUsage = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE_ARRAY
            );
            $arrayTypeUsage = $arrayTypeUsage->addArgToPayload($isNullable);
            $arrayTypeUsage = $arrayTypeUsage->addArgToPayload($arrayDimension);
            $arrayTypeUsage = $arrayTypeUsage->addArgToPayload($elementTypeNode);

            // Wrap in TYPE_USAGE
            return $typeUsageWrapper->addArgToPayload($arrayTypeUsage);
        } catch (Exception $e) {
            $this->logger->trackError('Error generating array type model', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Calculates array dimension by counting [] brackets in type name.
     */
    private function calculateArrayDimension(string $typeName): int
    {
        // Count occurrences of [] pattern
        $dimension = 0;
        $remaining = $typeName;
        
        while (preg_match('/\[]/', $remaining)) {
            $dimension++;
            $remaining = preg_replace('/\[]/', '', $remaining, 1);
        }
        
        // If no brackets found but type is 'array', assume dimension 1
        if ($dimension === 0 && (strtolower($typeName) === TYPES::ARRAY || str_contains($typeName, TYPES::ARRAY))) {
            return 1;
        }
        
        return $dimension;
    }

    /**
     * Extracts the element type from array type name (removes [] brackets).
     */
    private function extractElementType(string $typeName): string
    {
        // Remove all [] brackets
        $elementType = preg_replace('/\[]/', '', $typeName);
        
        // If nothing left or just 'array', default to 'mixed'
        if (empty($elementType) || strtolower($elementType) === TYPES::ARRAY) {
            return Types::MIXED;
        }
        
        return $elementType;
    }

    private function resolveElementType(string $typeName, Handlers $handlers): string
    {
        $elementType = $this->extractElementType($typeName);

        if ($elementType !== Types::MIXED) {
            return TypeNameResolver::resolveRelativeTypeName($elementType, $handlers);
        }

        $parameterName = $handlers->CURRENT_REFLECTION_PARAMETER_NAME !== ''
            ? $handlers->CURRENT_REFLECTION_PARAMETER_NAME
            : null;

        $fromDocblock = TypeNameResolver::parseArrayElementTypeFromDocblock(
            $handlers->CURRENT_REFLECTION_METHOD,
            $parameterName
        );

        if ($fromDocblock !== null) {
            return TypeNameResolver::resolveRelativeTypeName($fromDocblock, $handlers);
        }

        return Types::MIXED;
    }

    /**
     * @throws Exception
     */
    private function generateElementTypeUsageNode(
        string $elementTypeName,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): ?UnifiedGraftModel {
        $elementTypeObject = new readonly class($elementTypeName) {
            public function __construct(public string $type) {}

            public function __toString(): string
            {
                return $this->type;
            }
        };

        if ($elementTypeName === Types::VOID || strtolower($elementTypeName) === Types::VOID) {
            return $this->dispatchElementTypeHandler(
                UnifiedGraftModelType::TYPE_USAGE_VOID,
                $elementTypeObject,
                $parentCommand,
                $handlers
            );
        }

        if (
            $elementTypeName === Types::ARRAY
            || strtolower($elementTypeName) === Types::ARRAY
            || str_contains($elementTypeName, Chars::EMPTY_ARRAY)
        ) {
            return $this->dispatchElementTypeHandler(
                UnifiedGraftModelType::TYPE_USAGE_ARRAY,
                $elementTypeObject,
                $parentCommand,
                $handlers
            );
        }

        if (
            strlen($elementTypeName) === 1
            && ctype_upper($elementTypeName)
            && !in_array(strtolower($elementTypeName), ['i', 'o', 'a', 'e'], true)
        ) {
            return $this->dispatchElementTypeHandler(
                UnifiedGraftModelType::TYPE_USAGE_GENERIC_ALIAS,
                $elementTypeObject,
                $parentCommand,
                $handlers
            );
        }

        return $this->dispatchElementTypeHandler(
            UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE,
            $elementTypeObject,
            $parentCommand,
            $handlers
        );
    }

    /**
     * @throws Exception
     */
    private function dispatchElementTypeHandler(
        UnifiedGraftModelType $handlerType,
        object $elementTypeObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): ?UnifiedGraftModel {
        $handler = $handlers->SHARED_HANDLER[$handlerType->value] ?? null;
        if (!$handler instanceof HandlersInterface) {
            return null;
        }

        $generated = $handler->generateUnifiedGraftModel($elementTypeObject, $parentCommand, $handlers);

        return $this->unwrapTypeUsageWrapper($generated);
    }

    private function unwrapTypeUsageWrapper(UnifiedGraftModel $model): UnifiedGraftModel
    {
        if (
            $model->getUnifiedGraftModelType() === UnifiedGraftModelType::TYPE_USAGE
            && count($model->getPayload()) === 1
            && $model->getPayload()[0] instanceof UnifiedGraftModel
        ) {
            return $model->getPayload()[0];
        }

        return $model;
    }
}
