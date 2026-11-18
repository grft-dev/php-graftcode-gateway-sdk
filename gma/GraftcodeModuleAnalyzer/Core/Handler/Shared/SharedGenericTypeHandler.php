<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\{
    Handlers,
    HandlersInterface
};
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel,
    Models\UnifiedGraftModelInterface
};

/**
 * Handler for generic types.
 * Note: PHP doesn't have true generics, but this handler provides
 * a modular way to handle generic-like types similar to netcore's SharedGenericTypeHandler.
 */
final class SharedGenericTypeHandler extends SharedTypeHandler
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModelInterface {
        try {
            // TYPE_USAGE wraps TYPE_USAGE_GENERIC per specification
            $typeUsageWrapper = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE
            );

            $typeName = $this->extractTypeName($analyzedObject);
            $isNullable = $this->getIsNullable($analyzedObject, $typeName);
            
            // Extract generic name (base type without generic parameters)
            $genericName = $this->extractGenericName($typeName);
            
            // Extract generic arguments (for PHP, this will typically be empty)
            $genericArguments = $this->extractGenericArguments($typeName);
            
            // Create TYPE_USAGE_NODE[] for each generic argument
            $typeUsageNodes = [];
            $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value] ?? null;
            
            foreach ($genericArguments as $argTypeName) {
                if ($typeHandler instanceof HandlersInterface) {
                    $argTypeObject = new readonly class($argTypeName) {
                        public function __construct(public string $type) {}
                        public function __toString(): string { return $this->type; }
                    };
                    
                    $typeUsageNode = $typeHandler->generateUnifiedGraftModel(
                        $argTypeObject,
                        $parentCommand,
                        $handlers
                    );
                    $typeUsageNodes[] = $typeUsageNode;
                } else {
                    // Fallback: create primitive type node
                    $primitiveHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE->value] ?? null;
                    if ($primitiveHandler instanceof HandlersInterface) {
                        $argTypeObject = new readonly class($argTypeName) {
                            public function __construct(public string $type) {}
                            public function __toString(): string { return $this->type; }
                        };
                        $typeUsageNodes[] = $primitiveHandler->generateUnifiedGraftModel(
                            $argTypeObject,
                            $parentCommand,
                            $handlers
                        );
                    }
                }
            }

            // TYPE_USAGE_GENERIC payload: ENUM:IS_NULLABLE, STRING:GENERIC_NAME, <TYPE_USAGE_NODE>[]
            $genericTypeUsage = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE_GENERIC
            );
            $genericTypeUsage = $genericTypeUsage->addArgToPayload($isNullable);
            $genericTypeUsage = $genericTypeUsage->addArgToPayload($genericName);
            
            // Add each TYPE_USAGE_NODE to payload
            foreach ($typeUsageNodes as $node) {
                $genericTypeUsage = $genericTypeUsage->addArgToPayload($node);
            }

            // Wrap in TYPE_USAGE
            return $typeUsageWrapper->addArgToPayload($genericTypeUsage);
        } catch (Exception $e) {
            $this->logger->trackError('Error generating generic type model', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extracts the generic name (base type) from a generic type string.
     * Example: "Collection<int>" -> "Collection"
     */
    private function extractGenericName(string $typeName): string
    {
        // Check for generic syntax like Collection<int> or Collection<int, string>
        if (preg_match('/^([^<]+)</', $typeName, $matches)) {
            return trim($matches[1]);
        }
        
        // If no generic syntax, return the type name as-is
        return $typeName;
    }

    /**
     * Extracts generic arguments from a generic type string.
     * Example: "Collection<int, string>" -> ["int", "string"]
     * Returns empty array for non-generic types.
     */
    private function extractGenericArguments(string $typeName): array
    {
        // Check for generic syntax like Collection<int> or Collection<int, string>
        if (preg_match('/<(.+)>/', $typeName, $matches)) {
            $argsString = $matches[1];
            // Split by comma, but be careful of nested generics
            $arguments = [];
            $currentArg = '';
            $depth = 0;
            
            for ($i = 0; $i < strlen($argsString); $i++) {
                $char = $argsString[$i];
                
                if ($char === '<') {
                    $depth++;
                    $currentArg .= $char;
                } elseif ($char === '>') {
                    $depth--;
                    $currentArg .= $char;
                } elseif ($char === ',' && $depth === 0) {
                    $arguments[] = trim($currentArg);
                    $currentArg = '';
                } else {
                    $currentArg .= $char;
                }
            }
            
            if (!empty($currentArg)) {
                $arguments[] = trim($currentArg);
            }
            
            return $arguments;
        }
        
        // No generic arguments found
        return [];
    }
}
