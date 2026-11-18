<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Utils;

final readonly class UtilsSimpleType
{
    private function __construct()
    {
    }

    public static function isFloat(mixed $value): bool
    {
        return is_float($value) && self::getDecimalPrecision($value) <= 6;
    }

    public static function isChar(mixed $value): bool
    {
        return is_string($value) && strlen($value) === 1;
    }

    public static function isByte(mixed $value): bool
    {
        return is_int($value) && $value >= -128 && $value <= 127;
    }

    public static function isInteger(mixed $value): bool
    {
        return is_int($value) && $value >= -2147483648 && $value <= 2147483647;
    }

    public static function isLong(mixed $value): bool
    {
        return is_int($value) && $value >= -9223372036854775808 && $value <= 9223372036854775807;
    }

    private static function getDecimalPrecision(float $number): int
    {
        $str = (string) $number;
        $decimalPos = strpos($str, '.');
        if ($decimalPos === false) {
            return 0;
        }

        return strlen($str) - $decimalPos - 1;
    }
}
