<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\Config;

final readonly class EnvironmentConfig
{
    private const string UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private function __construct()
    {}

    public static function getInstrumentationKey(): ?string
    {
        $applicationInsightsConnectionString = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        if (self::isEmpty($applicationInsightsConnectionString)) {
            return null;
        }

        $parts = [];
        parse_str(str_replace(';', '&', $applicationInsightsConnectionString), $parts);

        if (
            !isset($parts['InstrumentationKey'])
            || !is_string($parts['InstrumentationKey'])
            || self::isEmpty($parts['InstrumentationKey'])
            || self::isNotValid($parts['InstrumentationKey'])
        ) {
            return null;
        }

        return $parts['InstrumentationKey'];
    }

    private static function isEmpty(null|array|false|string $env): bool
    {
        if (empty($env)) {
            return true;
        }

        if (trim($env) === '') {
            return true;
        }

        return false;
    }

    private static function isNotValid(string $key): bool
    {
        return preg_match(self::UUID_V4_PATTERN, $key) !== 1;
    }
}
