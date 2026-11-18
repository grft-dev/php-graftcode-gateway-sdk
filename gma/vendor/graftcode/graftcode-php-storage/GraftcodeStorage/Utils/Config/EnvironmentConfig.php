<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Config;

use GraftcodeStorage\Utils\Enum\{
    EnvironmentKeys,
    StorageType
};
use GraftcodeStorage\Utils\Exception\Storage\EmptyAzureStorageEnvironmentException;

final readonly class EnvironmentConfig
{
    private function __construct()
    {}

    public static function getStorageType(): string
    {
        $storageType = getenv('STORAGE_TYPE');
        if (empty($storageType) || trim($storageType) === '') {
            return StorageType::AZURE->name;
        }

        return $storageType;
    }

    public static function getAzureStorageUrl(): string
    {
        return self::getValueForKeyOrThrow(EnvironmentKeys::AZURE_STORAGE_URL->name);
    }

    public static function getAzureStorageToken(): string
    {
        return self::getValueForKeyOrThrow(EnvironmentKeys::AZURE_STORAGE_TOKEN->name);
    }

    private static function getValueForKeyOrThrow(string $key): string
    {
        $value = getenv($key);
        if (empty($value) || trim($value) === '') {
            throw new EmptyAzureStorageEnvironmentException($key);
        }

        return $value;
    }
}
