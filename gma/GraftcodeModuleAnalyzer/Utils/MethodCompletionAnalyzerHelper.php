<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

final class MethodCompletionAnalyzerHelper
{
    /**
     * @return array{isAsyncCall: bool, hasDeferredReturn: bool}
     */
    public static function analyze(ReflectionMethod|ReflectionFunction $method): array
    {
        $returnTypeName = self::resolveReturnTypeName($method);
        $hasDeferredReturn = self::hasDeferredReturnTypeName($returnTypeName);
        $isAsyncCall = $hasDeferredReturn && self::isAsyncMethodName($method->getName());

        return [
            'isAsyncCall' => $isAsyncCall,
            'hasDeferredReturn' => $hasDeferredReturn || $isAsyncCall,
        ];
    }

    /**
     * PHP has no async methods; hosted libs use Promise-like returns. Methods named *Async
     * map to Hypertube async RPC (parity with C# async / Node async function).
     */
    private static function isAsyncMethodName(string $methodName): bool
    {
        return str_ends_with($methodName, 'Async');
    }

    private static function resolveReturnTypeName(ReflectionMethod|ReflectionFunction $method): ?string
    {
        $returnType = $method->getReturnType();
        if ($returnType === null) {
            return null;
        }

        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType) {
                    return $type->getName();
                }
            }

            return null;
        }

        if ($returnType instanceof ReflectionNamedType) {
            return $returnType->getName();
        }

        return null;
    }

    private static function hasDeferredReturnTypeName(?string $returnTypeName): bool
    {
        if ($returnTypeName === null || $returnTypeName === '') {
            return false;
        }

        if (AsyncWrapperTypeHelper::tryUnwrap($returnTypeName) !== null) {
            return true;
        }

        $simple = $returnTypeName;
        if (str_contains($simple, '\\')) {
            $parts = explode('\\', $simple);
            $simple = end($parts);
        }

        return in_array(
            $simple,
            [
                'Promise',
                'PromiseInterface',
                'Task',
                'ValueTask',
                'CompletableFuture',
                'CompletionStage',
                'Future',
                'Coroutine',
                'Awaitable',
            ],
            true
        );
    }
}
