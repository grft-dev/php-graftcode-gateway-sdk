<?php

declare(strict_types=1);

namespace GraftcodeStorage\Storage\Factory;

use GraftcodeStorage\Storage\{
    AzureBlobStorage,
    LocalStorage,
    StorageInterface
};
use GraftcodeStorage\Utils\{
    Config\EnvironmentConfig,
    Enum\StorageType,
    Exception\Storage\UnsupportedStorageTypeException,
    VO\Blob\BlobService
};

final class StorageFactory implements StorageFactoryInterface
{
    /**
     * @throws UnsupportedStorageTypeException
     */
    public static function createStorage(): StorageInterface
    {
        $storageType = EnvironmentConfig::getStorageType();
        return match ($storageType) {
            StorageType::AZURE->name => new AzureBlobStorage(
                new BlobService(
                    EnvironmentConfig::getAzureStorageUrl(),
                    EnvironmentConfig::getAzureStorageToken()
                )
            ),
            StorageType::LOCAL->name => new LocalStorage(),
            default => throw new UnsupportedStorageTypeException($storageType),
        };
    }
}
