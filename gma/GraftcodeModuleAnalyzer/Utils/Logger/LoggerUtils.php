<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Logger;

final readonly class LoggerUtils
{
    public static function getLogSafeValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value,
            default => gettype($value)
        };
    }

    public static function getLogValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            default => gettype($value)
        };
    }
}
