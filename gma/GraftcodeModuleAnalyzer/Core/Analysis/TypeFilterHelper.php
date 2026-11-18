<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Analysis;

use function array_filter;
use function array_map;
use function explode;
use function preg_match;
use function preg_quote;
use function str_replace;
use function trim;

final readonly class TypeFilterHelper
{
    /**
     * Checks if a type name matches any of the provided wildcard patterns.
     * Empty or whitespace-only filter means "analyze all types".
     *
     * @param string $typeName   Fully qualified type name (e.g., "App\\Services\\UserService")
     * @param string $typeFilter Comma-separated wildcard patterns (e.g., "App\\*, *Service, Test*")
     */
    public static function matchesFilter(string $typeName, string $typeFilter): bool
    {
        $trimmed = trim($typeFilter);
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
            if (self::matchesWildcardPattern($typeName, $pattern)) {
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
