<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum GraftCodePrimitiveTypeSize
{
    case BOOLEAN_SIZE;
    case BYTE_SIZE;
    case CHAR_SIZE;
    case INTEGER_SIZE;
    case FLOAT_SIZE;
    case LONG_SIZE;
    case DOUBLE_SIZE;
    case UNSIGNED_LONG_LONG_SIZE;
    case UNSIGNED_INTEGER_SIZE;
    case NULL_SIZE;

    public function getValue(): int
    {
        return match($this) {
            self::BOOLEAN_SIZE,
            self::BYTE_SIZE,
            self::CHAR_SIZE,
            self::NULL_SIZE => 1,

            self::INTEGER_SIZE,
            self::FLOAT_SIZE,
            self::UNSIGNED_INTEGER_SIZE => 4,

            self::LONG_SIZE,
            self::DOUBLE_SIZE,
            self::UNSIGNED_LONG_LONG_SIZE => 8,
        };
    }
}

