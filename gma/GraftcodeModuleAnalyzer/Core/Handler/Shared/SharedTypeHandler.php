<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    AsyncWrapperTypeHelper,
    Const\SpecialChars,
    Const\GraftCodePrimitiveTypeMapping,
    Const\Types,
    TypeAssemblyResolver,
    TypeNameResolver
};
use GraftcodeProtocol\{
    Enums\IsNullable,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel,
    Models\UnifiedGraftModelInterface
};
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class SharedTypeHandler extends BaseHandler implements HandlersInterface
{
    /**
     * Generates a UnifiedGraftModel for TYPE_USAGE.
     * 
     * This handler dispatches to specialized handlers based on the type:
     * - TYPE_USAGE_VOID for void types
     * - TYPE_USAGE_ARRAY for array types
     * - TYPE_USAGE_GENERIC for generic types
     * - TYPE_USAGE_PRIMITIVE for primitive types
     * 
     * Output structure (nested, matching netcore/JVM/Python/Node.js):
     * TYPE_USAGE (wrapper) -> payload[0] = specialized subtype model
     * 
     * For primitive types: TYPE_USAGE -> TYPE_USAGE_PRIMITIVE
     *   TYPE_USAGE_PRIMITIVE payload: [IS_NULLABLE, NAMESPACE, TYPENAME, PRIMITIVE_TYPE, ASSEMBLY_NAME, ASSEMBLY_VERSION]
     * 
     * For array types: TYPE_USAGE -> TYPE_USAGE_ARRAY
     *   TYPE_USAGE_ARRAY payload: [IS_NULLABLE, ARRAY_DIMENSION, TYPE_USAGE_NODE]
     * 
     * For void types: TYPE_USAGE -> TYPE_USAGE_VOID
     *   TYPE_USAGE_VOID payload: [] (empty)
     * 
     * For generic types: TYPE_USAGE -> TYPE_USAGE_GENERIC
     *   TYPE_USAGE_GENERIC payload: [IS_NULLABLE, GENERIC_NAME, TYPE_USAGE_NODE[]]
     * 
     * @param mixed $analyzedObject The object being analyzed (ReflectionType, etc.)
     * @param UnifiedGraftModel $parentCommand The parent command
     * @param Handlers $handlers Collection of handlers
     * @return UnifiedGraftModel
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModelInterface {
        try {
            $typeName = $this->extractTypeName($analyzedObject);

            $unwrappedAsync = AsyncWrapperTypeHelper::tryUnwrap($typeName);
            if ($unwrappedAsync !== null) {
                if ($unwrappedAsync === AsyncWrapperTypeHelper::VOID_INNER) {
                    $voidHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_VOID->value] ?? null;
                    if ($voidHandler instanceof HandlersInterface) {
                        return $voidHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
                    }
                }

                $innerTypeObject = new readonly class($unwrappedAsync) {
                    public function __construct(public string $type) {}
                    public function __toString(): string { return $this->type; }
                };

                return $this->generateUnifiedGraftModel($innerTypeObject, $parentCommand, $handlers);
            }

            if (str_contains($typeName, '<')) {
                $genericHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_GENERIC->value] ?? null;
                if ($genericHandler instanceof HandlersInterface) {
                    return $genericHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
                }
            }

            // Dispatch to appropriate handler based on type detection (similar to netcore)
            // Check for void first
            if ($typeName === Types::VOID || strtolower($typeName) === Types::VOID) {
                $voidHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_VOID->value] ?? null;
                if ($voidHandler instanceof HandlersInterface) {
                    return $voidHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
                }
            }

            // Check for arrays
            if ($typeName === Types::ARRAY || strtolower($typeName) === Types::ARRAY) {
                $arrayHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_ARRAY->value] ?? null;
                if ($arrayHandler instanceof HandlersInterface) {
                    return $arrayHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
                }
            }

            // Check for generic type parameters (single uppercase letters like T, K, V)
            if (strlen($typeName) === 1 && ctype_upper($typeName) && !in_array(strtolower($typeName), ['i', 'o', 'a', 'e'])) {
                $aliasHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_GENERIC_ALIAS->value] ?? null;
                if ($aliasHandler instanceof HandlersInterface) {
                    return $aliasHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
                }
            }

            // Default to primitive handler
            $primitiveHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE->value] ?? null;
            if ($primitiveHandler instanceof HandlersInterface) {
                return $primitiveHandler->generateUnifiedGraftModel($analyzedObject, $parentCommand, $handlers);
            }

            // Fallback to original implementation if handlers not available
            $type = $this->getTypeDescription($analyzedObject);
            $this->logger->trackVerbose(sprintf('Generating type usage model for type: {%s}', $type), [
                'type' => $type
            ]);

            $typeUsageWrapper = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE
            );

            $primitiveType = $this->mapToPrimitiveType($typeName);
            $isNullable = $this->getIsNullable($analyzedObject, $typeName);
            $namespace = TypeNameResolver::extractNamespace($typeName);
            $typeShortName = TypeNameResolver::extractShortName($typeName);
            $assembly = TypeAssemblyResolver::resolve($typeName, $primitiveType, $handlers);

            $command = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE
            );
            $command = $command->addArgToPayload($isNullable);
            $command = $command->addArgToPayload($namespace);
            $command = $command->addArgToPayload($typeShortName);
            $command = $command->addArgToPayload($primitiveType);
            $command = $command->addArgToPayload($assembly['assemblyName']);
            $command = $command->addArgToPayload($assembly['assemblyVersion']);

            $this->logger->trackVerbose(sprintf('Type usage model generated for type: {%s}, primitive type: {%s}', $typeName, $primitiveType), [
                'typeName' => $typeName,
                'primitiveType' => $primitiveType
            ]);

            return $typeUsageWrapper->addArgToPayload($command);
        } catch (Exception $e) {
            $typeDescription = $this->getTypeDescription($analyzedObject);
            $this->logger->trackError(sprintf('Error generating type usage model for: {%s}', $typeDescription), [
                'analyzedObject' => $typeDescription,
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function extractTypeName(object $analyzedObject): string
    {
        return match (true) {
            $analyzedObject instanceof ReflectionNamedType => $this->handleNamedType($analyzedObject),
            $analyzedObject instanceof ReflectionUnionType => $this->handleUnionType($analyzedObject),
            $analyzedObject instanceof ReflectionIntersectionType => $this->handleIntersectionType($analyzedObject),
            $analyzedObject instanceof ReflectionClass => $analyzedObject->getName(),
            default => $this->handleGenericObject($analyzedObject)
        };
    }

    private function handleNamedType(ReflectionNamedType $type): string
    {
        return $type->getName();
    }

    private function handleUnionType(ReflectionUnionType $unionType): string
    {
        $types = [];
        foreach ($unionType->getTypes() as $type) {
            $types[] = match (true) {
                $type instanceof ReflectionNamedType => $type->getName(),
                default => (string) $type
            };
        }

        return implode(SpecialChars::OR, $types);
    }

    private function handleIntersectionType(ReflectionIntersectionType $intersectionType): string
    {
        $types = [];
        foreach ($intersectionType->getTypes() as $type) {
            $types[] = match (true) {
                $type instanceof ReflectionNamedType => $type->getName(),
                default => (string) $type
            };
        }

        return implode(SpecialChars::END, $types);
    }

    private function handleGenericObject(object $analyzedObject): string
    {
        if (method_exists($analyzedObject, '__toString')) {
            return (string) $analyzedObject;
        }

        return get_class($analyzedObject);
    }

    protected function mapToPrimitiveType(string $typeName): int
    {
        $cleanTypeName = $this->normalizeTypeName($typeName);

        // Handle union types
        if (str_contains($cleanTypeName, SpecialChars::OR)) {
            return $this->handleUnionTypePrimitive($cleanTypeName);
        }

        // Handle intersection types
        if (str_contains($cleanTypeName, SpecialChars::END)) {
            return GraftCodePrimitiveTypeMapping::OBJECT;
        }

        // Handle array types
        if (str_ends_with($typeName, SpecialChars::SQUARE_BRACKET) || str_starts_with($typeName, Types::ARRAY)) {
            return GraftCodePrimitiveTypeMapping::ARRAY;
        }

        // Handle nullable types
        if (str_starts_with($cleanTypeName, SpecialChars::QUESTION_MARK)) {
            $cleanTypeName = substr($cleanTypeName, 1);
        }

        $graftCodePrimitiveType = GraftCodePrimitiveTypeMapping::fromString($cleanTypeName);
        if (null !== $graftCodePrimitiveType) {
            return $graftCodePrimitiveType;
        }

        // Handle class types and other complex types
        return $this->mapComplexType($cleanTypeName);
    }

    private function normalizeTypeName(string $typeName): string
    {
        return strtolower(
            str_replace([SpecialChars::BACKSLASH, 'System.', 'java.lang.'], '', trim($typeName))
        );
    }

    private function handleUnionTypePrimitive(string $unionType): int
    {
        $types = explode(SpecialChars::OR, $unionType);
        $types = array_map('trim', $types);

        if (in_array(Types::NULL, $types)) {
            $nonNullTypes = array_filter($types, fn($t) => $t !== Types::NULL);
            if (count($nonNullTypes) === 1) {
                $singleType = reset($nonNullTypes);
                return GraftCodePrimitiveTypeMapping::fromString($singleType) ?? GraftCodePrimitiveTypeMapping::OBJECT;
            }
        }

        // Multiple non-null types = complex object
        return GraftCodePrimitiveTypeMapping::OBJECT;
    }

    private function mapComplexType(string $typeName): int
    {
        return match (true) {
            str_contains($typeName, Types::CLOSURE) || str_contains($typeName, Types::CALLABLE) => GraftCodePrimitiveTypeMapping::CLOSURE,
            str_contains($typeName, Types::ITERATOR), str_contains($typeName, Types::GENERATOR) => GraftCodePrimitiveTypeMapping::ITERABLE,
            default => GraftCodePrimitiveTypeMapping::OBJECT
        };
    }

    private function getTypeDescription(object $analyzedObject): string
    {
        return match (true) {
            $analyzedObject instanceof ReflectionType, method_exists($analyzedObject, '__toString') => (string) $analyzedObject,
            $analyzedObject instanceof ReflectionClass => $analyzedObject->getName(),
            default => get_class($analyzedObject)
        };
    }

    /**
     * Determines if a type is nullable based on the analyzed object and type name.
     * A type is nullable if:
     * - The ReflectionNamedType allows null
     * - The type name contains 'null' in a union type
     * - The type name starts with '?' (PHP nullable type syntax)
     *
     * @param object $analyzedObject The analyzed object (ReflectionType, ReflectionClass, etc.)
     * @param string $typeName The extracted type name
     * @return int IsNullable::TRUE (1) if nullable, IsNullable::FALSE (0) otherwise
     */
    protected function getIsNullable(object $analyzedObject, string $typeName): int
    {
        // Check if ReflectionNamedType allows null
        if ($analyzedObject instanceof ReflectionNamedType && $analyzedObject->allowsNull()) {
            return IsNullable::TRUE->value;
        }

        // Check for union types with null
        if ($analyzedObject instanceof ReflectionUnionType) {
            foreach ($analyzedObject->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType && $type->getName() === 'null') {
                    return IsNullable::TRUE->value;
                }
            }
        }

        // Check for PHP nullable type syntax (?Type)
        if (str_starts_with($typeName, SpecialChars::QUESTION_MARK)) {
            return IsNullable::TRUE->value;
        }

        // Check if type name contains 'null' in union
        if (str_contains($typeName, SpecialChars::OR)) {
            $types = explode(SpecialChars::OR, $typeName);
            $types = array_map('trim', $types);
            if (in_array(Types::NULL, $types, true)) {
                return IsNullable::TRUE->value;
            }
        }

        return IsNullable::FALSE->value;
    }

    /**
     * Builds TYPE_USAGE -> TYPE_USAGE_PRIMITIVE for a resolved type name.
     *
     * @throws Exception
     */
    public function buildPrimitiveTypeUsage(
        string $resolvedTypeName,
        int $isNullable,
        Handlers $handlers
    ): UnifiedGraftModelInterface {
        $primitiveType = $this->mapToPrimitiveType($resolvedTypeName);
        $namespace = TypeNameResolver::extractNamespace($resolvedTypeName);
        $typeName = TypeNameResolver::extractShortName($resolvedTypeName);
        $assembly = TypeAssemblyResolver::resolve($resolvedTypeName, $primitiveType, $handlers);

        $typeUsageWrapper = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_USAGE
        );

        $command = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE
        );
        $command = $command->addArgToPayload($isNullable);
        $command = $command->addArgToPayload($namespace);
        $command = $command->addArgToPayload($typeName);
        $command = $command->addArgToPayload($primitiveType);
        $command = $command->addArgToPayload($assembly['assemblyName']);
        $command = $command->addArgToPayload($assembly['assemblyVersion']);

        return $typeUsageWrapper->addArgToPayload($command);
    }
}
