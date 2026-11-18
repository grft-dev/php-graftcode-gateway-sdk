<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Analysis;

final readonly class MethodFilterHelper
{
    public static function matchesFilter(string $methodName, string $methodFilter, ?string $typeFullName = null): bool
    {
        $trimmed = trim($methodFilter);
        if ($trimmed === '') {
            return true;
        }

        $patterns = array_filter(
            array_map(trim(...), explode(',', $trimmed)),
            static fn(string $p): bool => $p !== '',
        );

        if ($patterns === []) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if (self::matchesWildcardPattern($methodName, $pattern)) {
                return true;
            }

            if ($typeFullName !== null && $typeFullName !== ''
                && self::matchesWildcardPattern($typeFullName . '.' . $methodName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesWildcardPattern(string $input, string $pattern): bool
    {
        $regex = '/^'
            . str_replace(
                ['\\*', '\\?'],
                ['.*', '.'],
                preg_quote($pattern, '/'),
            )
            . '$/i';

        return preg_match($regex, $input) === 1;
    }
}
