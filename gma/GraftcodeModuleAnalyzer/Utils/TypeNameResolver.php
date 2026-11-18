<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use GraftcodeModuleAnalyzer\Core\Handler\Handlers;
use GraftcodeModuleAnalyzer\Utils\Const\SpecialChars;
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class TypeNameResolver
{
    /**
     * @throws ReflectionException
     */
    public static function resolveRelativeTypeName(string $typeName, Handlers $handlers): string
    {
        return match (strtolower($typeName)) {
            Types::SELF, Types::STATIC => $handlers->CURRENT_DECLARING_TYPE_FQCN !== ''
                ? $handlers->CURRENT_DECLARING_TYPE_FQCN
                : $typeName,
            Types::PARENT => self::resolveParent($handlers),
            default => $typeName,
        };
    }

    /**
     * @throws ReflectionException
     */
    public static function resolveParent(Handlers $handlers): string
    {
        if ($handlers->CURRENT_DECLARING_TYPE_FQCN === '') {
            return Types::PARENT;
        }

        $parent = new ReflectionClass($handlers->CURRENT_DECLARING_TYPE_FQCN)->getParentClass();

        return $parent !== false ? $parent->getName() : Types::PARENT;
    }

    public static function getNamespaceFromFqcn(string $fqcn): string
    {
        $cleanFqcn = ltrim($fqcn, SpecialChars::QUESTION_MARK);

        if (false === str_contains($cleanFqcn, '\\')) {
            return '';
        }

        $parts = explode('\\', $cleanFqcn);
        array_pop($parts);

        return implode('\\', $parts);
    }

    public static function getShortNameFromType(string $typeName): string
    {
        $clean = ltrim($typeName, SpecialChars::QUESTION_MARK);

        if (false === str_contains($clean, '\\')) {
            return $clean;
        }

        $parts = explode('\\', $clean);

        return (string) end($parts);
    }

    public static function extractNamespace(string $typeName): string
    {
        $clean = ltrim($typeName, SpecialChars::QUESTION_MARK);
        if (self::isStringContainsORorEndChar($clean)) {
            return '';
        }

        if (self::isStringNotContainsBackslash($clean)) {
            return '';
        }

        return self::getNamespaceFromFqcn($clean);
    }
    private static function isStringContainsORorEndChar(string $clean): bool
    {
        return str_contains($clean, SpecialChars::OR)
            || str_contains($clean, SpecialChars::END);
    }

    private static function isStringNotContainsBackslash(string $clean): bool
    {
        return false === str_contains($clean, '\\');
    }

    public static function extractShortName(string $typeName): string
    {
        $clean = ltrim($typeName, SpecialChars::QUESTION_MARK);
        if (self::isStringContainsORorEndChar($clean)) {
            return $clean;
        }

        if (self::isStringNotContainsBackslash($clean)) {
            return $clean;
        }

        return self::getShortNameFromType($clean);
    }

    public static function parseArrayElementTypeFromDocblock(
        ?ReflectionMethod $method,
        ?string $parameterName = null
    ): ?string {
        if ($method === null) {
            return null;
        }

        $docComment = $method->getDocComment();
        if ($docComment === false || $docComment === '') {
            return null;
        }

        if ($parameterName !== null && $parameterName !== '') {
            $pattern = '/@param\s+(\S+)\s+\$' . preg_quote($parameterName, '/') . '\b/';
            if (preg_match($pattern, $docComment, $matches) === 1) {
                return self::extractArrayElementFromTypeString($matches[1]);
            }

            return null;
        }

        if (preg_match('/@return\s+(\S+)/', $docComment, $matches) === 1) {
            return self::extractArrayElementFromTypeString($matches[1]);
        }

        return null;
    }

    private static function extractArrayElementFromTypeString(string $type): ?string
    {
        if (preg_match('/^(.+)\[]$/', $type, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^array<([^,>]+)/', $type, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
