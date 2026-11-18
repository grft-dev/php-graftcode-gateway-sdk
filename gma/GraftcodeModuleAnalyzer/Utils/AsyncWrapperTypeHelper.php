<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

/**
 * Strips async wrapper generics from PHPDoc / string type names for UGM TYPE_USAGE (mirrors Node GMA).
 */
final class AsyncWrapperTypeHelper
{
    public const string VOID_INNER = '__async_void__';

    private const array ASYNC_WRAPPER_NAMES = [
        'Promise',
        'Task',
        'ValueTask',
        'CompletableFuture',
        'CompletionStage',
        'Future',
        'Coroutine',
        'Awaitable',
    ];

    private const array ASYNC_SEQUENCE_WRAPPER_NAMES = [
        'IAsyncEnumerable',
    ];

    /**
     * @return string|null Unwrapped type string, VOID_INNER for void inner, or null when not a wrapper.
     */
    public static function tryUnwrap(string $typeName): ?string
    {
        $trimmed = trim($typeName);
        if (!preg_match('/^([\w.\\\\]+)<(.+)>$/', $trimmed, $matches)) {
            return null;
        }

        $name = $matches[1];
        if (str_contains($name, '\\')) {
            $parts = explode('\\', $name);
            $name = end($parts);
        }

        $argsString = $matches[2];
        $args = self::parseGenericArguments($argsString);
        if ($args === []) {
            return null;
        }

        if (in_array($name, self::ASYNC_SEQUENCE_WRAPPER_NAMES, true)) {
            $inner = trim($args[0]);
            return str_ends_with($inner, '[]') ? $inner : $inner . '[]';
        }

        if (!in_array($name, self::ASYNC_WRAPPER_NAMES, true)) {
            return null;
        }

        $innerArg = trim($args[0]);
        if ($innerArg === 'void' || $innerArg === 'undefined') {
            return self::VOID_INNER;
        }

        $nested = self::tryUnwrap($innerArg);
        return $nested ?? $innerArg;
    }

    /**
     * @return list<string>
     */
    private static function parseGenericArguments(string $argsString): array
    {
        $arguments = [];
        $currentArg = '';
        $depth = 0;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
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

        if ($currentArg !== '') {
            $arguments[] = trim($currentArg);
        }

        return $arguments;
    }
}
