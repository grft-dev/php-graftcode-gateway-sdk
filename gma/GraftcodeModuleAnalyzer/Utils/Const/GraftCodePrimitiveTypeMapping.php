<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Const;

use GraftcodeProtocol\Enums\GraftCodePrimitiveType;

final readonly class GraftCodePrimitiveTypeMapping
{
    public const int STRING = GraftCodePrimitiveType::STRING->value;
    public const int INTEGER = GraftCodePrimitiveType::INTEGER->value;
    public const int BOOLEAN = GraftCodePrimitiveType::BOOLEAN->value;
    public const int FLOAT = GraftCodePrimitiveType::FLOAT->value;
    public const int DOUBLE = GraftCodePrimitiveType::DOUBLE->value;
    public const int NULL = GraftCodePrimitiveType::NULL->value;
    public const int RESOURCE = GraftCodePrimitiveType::BYTE->value;
    public const int CHAR = GraftCodePrimitiveType::CHAR->value;
    public const int LONG = GraftCodePrimitiveType::LONG->value;
    public const int ARRAY = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int OBJECT = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int CALLABLE = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int ITERABLE = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int SELF = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int MIXED = GraftCodePrimitiveType::UNKNOWN->value;
    public const int NEVER = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int PARENT = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int STATIC = GraftCodePrimitiveType::GRAFT_MODEL->value;
    public const int UNSIGNED_INTEGER = GraftCodePrimitiveType::UNSIGNED_INTEGER->value;
    public const int UNSIGNED_LONG_LONG = GraftCodePrimitiveType::UNSIGNED_LONG_LONG->value;
    public const int CLOSURE = GraftCodePrimitiveType::DELEGATE->value;

    public static function fromString(string $type): ?int
    {
        return match (strtolower($type)) {
            Types::STRING => self::STRING,
            Types::INT, 'integer' => self::INTEGER,
            Types::BOOL, 'boolean' => self::BOOLEAN,
            Types::FLOAT => self::FLOAT,
            'double' => self::DOUBLE,
            Types::NULL => self::NULL,
            'resource' => self::RESOURCE,
            'char' => self::CHAR,
            'long' => self::LONG,
            Types::ARRAY => self::ARRAY,
            'object' => self::OBJECT,
            Types::CALLABLE => self::CALLABLE,
            'iterable' => self::ITERABLE,
            Types::SELF => self::SELF,
            Types::MIXED => self::MIXED,
            'never' => self::NEVER,
            Types::PARENT => self::PARENT,
            Types::STATIC => self::STATIC,
            'unsigned_integer' => self::UNSIGNED_INTEGER,
            'unsigned_long_long' => self::UNSIGNED_LONG_LONG,
            Types::CLOSURE => self::CLOSURE,
            default => null,
        };
    }
}
