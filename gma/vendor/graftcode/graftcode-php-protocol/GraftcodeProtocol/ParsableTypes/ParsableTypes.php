<?php

declare(strict_types=1);

namespace GraftcodeProtocol\ParsableTypes;

use GraftcodeProtocol\Const\ParsableTypeNames;
use GraftcodeProtocol\Enums\ParsableTypesEnum;

/**
 * Cross-runtime catalog of type names that have a stable wire/string parse
 * representation. The same identifiers are listed in every protocol binding
 * so consumers can membership-check without branching on runtime.
 *
 * {@see mapToLocalType} returns idiomatic PHP types aligned with Hypertube PHP SDK
 * parsers (DateTimeImmutable, DateInterval, HypertubePhpUtils\*).
 */
final readonly class ParsableTypes
{
    /** @var list<string> */
    public const array PARSABLE_TYPE_NAMES = [
        ParsableTypeNames::DATE,
        ParsableTypeNames::SYSTEM_DATETIME,
        ParsableTypeNames::SYSTEM_DATETIME_OFFSET,
        ParsableTypeNames::URL,
        ParsableTypeNames::JAVA_AWT_COLOR,
        ParsableTypeNames::JAVA_AWT_POINT,
        ParsableTypeNames::JAVA_NET_URI,
        ParsableTypeNames::JAVA_TIME_DURATION,
        ParsableTypeNames::JAVA_TIME_INSTANT,
        ParsableTypeNames::JAVA_TIME_LOCAL_TIME,
        ParsableTypeNames::JAVA_TIME_OFFSET_DATE_TIME,
        ParsableTypeNames::JAVA_UTIL_UUID,
        ParsableTypeNames::SYSTEM_DRAWING_COLOR,
        ParsableTypeNames::SYSTEM_DRAWING_POINT,
        ParsableTypeNames::DATETIME_DATETIME,
        ParsableTypeNames::DATETIME_TIMEDELTA,
        ParsableTypeNames::DATETIME_TIME,
        ParsableTypeNames::SYSTEM_GUID,
        ParsableTypeNames::SYSTEM_TIME_ONLY,
        ParsableTypeNames::SYSTEM_TIME_SPAN,
        ParsableTypeNames::SYSTEM_URI,
        ParsableTypeNames::UUID_UUID,
    ];

    /** @var array<string, string> */
    private const array FOREIGN_TO_CATEGORY = [
        ParsableTypeNames::DATE => ParsableTypesEnum::PARSED_DATETIME->name,
        ParsableTypeNames::SYSTEM_DATETIME => ParsableTypesEnum::PARSED_DATETIME->name,
        ParsableTypeNames::JAVA_TIME_INSTANT => ParsableTypesEnum::PARSED_DATETIME->name,
        ParsableTypeNames::DATETIME_DATETIME => ParsableTypesEnum::PARSED_DATETIME->name,
        ParsableTypeNames::SYSTEM_DATETIME_OFFSET => ParsableTypesEnum::PARSED_DATETIME_OFFSET->name,
        ParsableTypeNames::JAVA_TIME_OFFSET_DATE_TIME => ParsableTypesEnum::PARSED_DATETIME_OFFSET->name,
        ParsableTypeNames::JAVA_TIME_LOCAL_TIME => ParsableTypesEnum::PARSED_TIME->name,
        ParsableTypeNames::SYSTEM_TIME_ONLY => ParsableTypesEnum::PARSED_TIME->name,
        ParsableTypeNames::DATETIME_TIME => ParsableTypesEnum::PARSED_TIME->name,
        ParsableTypeNames::JAVA_TIME_DURATION => ParsableTypesEnum::PARSED_DURATION->name,
        ParsableTypeNames::SYSTEM_TIME_SPAN => ParsableTypesEnum::PARSED_DURATION->name,
        ParsableTypeNames::DATETIME_TIMEDELTA => ParsableTypesEnum::PARSED_DURATION->name,
        ParsableTypeNames::JAVA_UTIL_UUID => ParsableTypesEnum::PARSED_UUID->name,
        ParsableTypeNames::SYSTEM_GUID => ParsableTypesEnum::PARSED_UUID->name,
        ParsableTypeNames::UUID_UUID => ParsableTypesEnum::PARSED_UUID->name,
        ParsableTypeNames::JAVA_NET_URI => ParsableTypesEnum::PARSED_URI->name,
        ParsableTypeNames::SYSTEM_URI => ParsableTypesEnum::PARSED_URI->name,
        ParsableTypeNames::URL => ParsableTypesEnum::PARSED_URI->name,
        ParsableTypeNames::JAVA_AWT_POINT => ParsableTypesEnum::PARSED_POINT->name,
        ParsableTypeNames::SYSTEM_DRAWING_POINT => ParsableTypesEnum::PARSED_POINT->name,
        ParsableTypeNames::JAVA_AWT_COLOR => ParsableTypesEnum::PARSED_COLOR->name,
        ParsableTypeNames::SYSTEM_DRAWING_COLOR => ParsableTypesEnum::PARSED_COLOR->name,
    ];

    /** @var array<string, string> */
    private const array CATEGORY_TO_LOCAL = [
        ParsableTypesEnum::PARSED_DATETIME->name => ParsableTypeNames::DATETIMEIMMUTABLE,
        ParsableTypesEnum::PARSED_DATETIME_OFFSET->name => ParsableTypeNames::DATETIMEIMMUTABLE,
        ParsableTypesEnum::PARSED_TIME->name => ParsableTypeNames::DATETIMEIMMUTABLE,
        ParsableTypesEnum::PARSED_DURATION->name => ParsableTypeNames::DATEINTERVAL,
        ParsableTypesEnum::PARSED_UUID->name => ParsableTypeNames::HYPERTUBE_PHP_UUID,
        ParsableTypesEnum::PARSED_URI->name => ParsableTypeNames::HYPERTUBE_PHP_URI,
        ParsableTypesEnum::PARSED_POINT->name => ParsableTypeNames::HYPERTUBE_PHP_POINT,
        ParsableTypesEnum::PARSED_COLOR->name => ParsableTypeNames::HYPERTUBE_PHP_COLOR,
    ];

    public static function isParsableType(?string $typeFullName): bool
    {
        if ($typeFullName === null || trim($typeFullName) === '') {
            return false;
        }

        return in_array(trim($typeFullName), self::PARSABLE_TYPE_NAMES, true);
    }

    /**
     * Maps any cross-runtime parsable type name to the idiomatic PHP type for the
     * same semantic group. Returns an empty string when the input is blank or not
     * a recognized parsable type.
     */
    public static function mapToLocalType(?string $foreignType): string
    {
        if ($foreignType === null || trim($foreignType) === '') {
            return '';
        }

        $category = self::FOREIGN_TO_CATEGORY[trim($foreignType)] ?? null;
        if ($category === null) {
            return '';
        }

        return self::CATEGORY_TO_LOCAL[$category] ?? '';
    }
}
