<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Enum;

enum HttpStatusCode: int
{
    case OK = 200;

    case CREATED = 201;

    case ACCEPTED = 202;

    case NOT_FOUND = 404;

    case INTERNAL_SERVER_ERROR = 500;

    public static function isNotAllowed(int $statusCode): bool
    {
        return !in_array($statusCode, [
            self::ACCEPTED->value,
            self::NOT_FOUND->value,
        ], true);
    }

    public static function isOk(int $statusCode): bool
    {
        return self::OK->value === $statusCode;
    }

    public static function isNotFound(int $statusCode): bool
    {
        return self::NOT_FOUND->value === $statusCode;
    }

    public static function isNotOk(int $statusCode): bool
    {
        return self::OK->value !== $statusCode;
    }

    public static function isNotCreated(int $statusCode): bool
    {
        return self::CREATED->value !== $statusCode;
    }
}
