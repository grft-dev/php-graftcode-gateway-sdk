<?php

declare(strict_types=1);

namespace GraftcodeProtocol\CommentDoc;

final class TypeMapping
{
    private const PHP_RAW_TO_NORMALIZED = [
        'string' => NormalizedType::STRING,
        'str' => NormalizedType::STRING,
        'int' => NormalizedType::INTEGER,
        'integer' => NormalizedType::INTEGER,
        'long' => NormalizedType::INTEGER,
        'float' => NormalizedType::NUMBER,
        'double' => NormalizedType::NUMBER,
        'real' => NormalizedType::NUMBER,
        'decimal' => NormalizedType::NUMBER,
        'bool' => NormalizedType::BOOLEAN,
        'boolean' => NormalizedType::BOOLEAN,
        'true' => NormalizedType::BOOLEAN,
        'false' => NormalizedType::BOOLEAN,
        'array' => NormalizedType::ARRAY,
        'iterable' => NormalizedType::ARRAY,
        'list' => NormalizedType::ARRAY,
        'object' => NormalizedType::OBJECT,
        'stdclass' => NormalizedType::OBJECT,
        'mixed' => NormalizedType::UNKNOWN,
        'null' => NormalizedType::VOID,
        'void' => NormalizedType::VOID,
        'callable' => NormalizedType::OBJECT,
        'closure' => NormalizedType::OBJECT,
        'datetime' => NormalizedType::DATETIME,
        'datetimeimmutable' => NormalizedType::DATETIME,
        'enum' => NormalizedType::ENUM,
        'datetimeinterface' => NormalizedType::DATETIME,
        'resource' => NormalizedType::OBJECT,
        'uuid' => NormalizedType::STRING,
        'uuidinterface' => NormalizedType::STRING,
        'uuidv4' => NormalizedType::STRING,
        'uuidv7' => NormalizedType::STRING,
        'symbolicuuid' => NormalizedType::STRING,
        'ulid' => NormalizedType::STRING,
    ];

    public static function normalizedToPhpType(?string $rawType, ?string $normalizedType): string
    {
        return NormalizedType::normalizedToPhpType($rawType, $normalizedType);
    }

    public static function resolveNormalizedType(?string $rawType): ?NormalizedType
    {
        if (self::isArrayLikeDocType($rawType)) {
            return NormalizedType::ARRAY;
        }

        $lookupKey = self::extractLookupKey($rawType);
        if ($lookupKey === null) {
            return null;
        }

        return self::PHP_RAW_TO_NORMALIZED[$lookupKey] ?? null;
    }

    public static function phpRawToNormalized(?string $rawType): string
    {
        return self::resolveNormalizedType($rawType)?->value ?? '';
    }

    private static function isArrayLikeDocType(?string $rawType): bool
    {
        if ($rawType === null || trim($rawType) === '') {
            return false;
        }

        $base = trim($rawType);
        $base = preg_replace('/^\?/', '', $base) ?? $base;

        $members = preg_split('/\s*[|&]\s*/', $base) ?: [$base];
        foreach ($members as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || strtolower($candidate) === 'null') {
                continue;
            }

            if (self::isArrayLikeDocTypeMember($candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function isArrayLikeDocTypeMember(string $type): bool
    {
        $type = ltrim(trim($type), '\\');

        if (preg_match('/\[\]\s*$/', $type) === 1) {
            return true;
        }

        return preg_match('/^(array|list|iterable)\s*</i', $type) === 1;
    }

    private static function extractLookupKey(?string $rawType): ?string
    {
        if ($rawType === null || trim($rawType) === '') {
            return null;
        }

        $base = trim($rawType);
        $base = preg_replace('/^\?/', '', $base) ?? $base;

        $members = preg_split('/\s*[|&]\s*/', $base) ?: [$base];
        $first = $members[0] ?? $base;
        foreach ($members as $candidate) {
            $candidateLower = strtolower(ltrim(trim($candidate), '\\'));
            if ($candidateLower !== 'null' && $candidateLower !== '') {
                $first = $candidate;
                break;
            }
        }

        $first = ltrim(trim($first), '\\');
        $first = preg_split('/[<\[\s]/', $first)[0] ?? $first;

        $lastSeparator = strrpos($first, '\\');
        if ($lastSeparator !== false) {
            $first = substr($first, $lastSeparator + 1);
        }

        return strtolower($first);
    }
}
