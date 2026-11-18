<?php

declare(strict_types=1);

namespace GraftcodeProtocol\CommentDoc;

/**
 * Unified CommentDoc vocabulary (wire values) plus PHP-idiomatic PHPDoc spellings.
 *
 * Wire cases match the neutral ``normalized`` field in UGM (PDF Section 7).
 * PHP_* cases are used only as denormalize targets for {@see self::toPhpType()}.
 */
enum NormalizedType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case UNKNOWN = 'unknown';
    case VOID = 'void';
    case DATETIME = 'datetime';
    case DATE = 'date';
    case MAP = 'map';
    case ENUM = 'enum';
    case ERROR = 'error';

    case PHP_INT = 'int';
    case PHP_FLOAT = 'float';
    case PHP_BOOL = 'bool';
    case PHP_MIXED = 'mixed';
    case PHP_DATETIME_INTERFACE = '\\DateTimeInterface';
    case PHP_THROWABLE = '\\Throwable';

    public function toPhpType(): string
    {
        return match ($this) {
            self::STRING => self::STRING->value,
            self::INTEGER => self::PHP_INT->value,
            self::NUMBER => self::PHP_FLOAT->value,
            self::BOOLEAN => self::PHP_BOOL->value,
            self::DATETIME, self::DATE => self::PHP_DATETIME_INTERFACE->value,
            self::ARRAY, self::MAP => self::ARRAY->value,
            self::OBJECT => self::OBJECT->value,
            self::VOID => self::VOID->value,
            self::UNKNOWN => self::PHP_MIXED->value,
            self::ENUM => self::STRING->value,
            self::ERROR => self::PHP_THROWABLE->value,
        };
    }

    public static function tryFromWireValue(?string $normalizedType): ?self
    {
        $key = strtolower(trim((string) $normalizedType));
        if ($key === '') {
            return null;
        }

        return self::tryFrom($key);
    }

    public static function normalizedToPhpType(?string $rawType, ?string $normalizedType): string
    {
        $wire = self::tryFromWireValue($normalizedType);
        if ($wire !== null) {
            return $wire->toPhpType();
        }

        return trim((string) $rawType);
    }
}
