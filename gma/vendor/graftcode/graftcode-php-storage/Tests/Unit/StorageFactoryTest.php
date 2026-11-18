<?php

declare(strict_types=1);

namespace Unit;

use GraftcodeStorage\Storage\{
    AzureBlobStorage,
    Factory\StorageFactory,
    LocalStorage
};
use GraftcodeStorage\Utils\{
    Enum\StorageType,
    Exception\Storage\UnsupportedStorageTypeException
};
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};

final class StorageFactoryTest extends TestCase
{
    #[Group('unit')]
    public function testCreateStorageReturnsLocalStorage(): void
    {
        $storageKey = getenv('STORAGE_TYPE');
        putenv('STORAGE_TYPE=' . StorageType::LOCAL->name);

        try {
            $storage = StorageFactory::createStorage();

            $this->assertInstanceOf(LocalStorage::class, $storage);
        } finally {
            if ($storageKey !== false) {
                putenv('STORAGE_TYPE=' . $storageKey);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }

    #[Group('unit')]
    public function testCreateStorageReturnsAzureStorage(): void
    {
        $storageKey = getenv('STORAGE_TYPE');
        putenv('STORAGE_TYPE=' . StorageType::AZURE->name);

        $azureUrlKey = getenv('AZURE_STORAGE_URL');
        putenv('AZURE_STORAGE_URL=https://test.azure-storage.net/');

        $azureTokenKey = getenv('AZURE_STORAGE_TOKEN');
        putenv('AZURE_STORAGE_TOKEN=sv=2022-11-02&test-token');

        try {
            $storage = StorageFactory::createStorage();

            $this->assertInstanceOf(AzureBlobStorage::class, $storage);
        } finally {
            if ($storageKey !== false) {
                putenv('STORAGE_TYPE=' . $storageKey);
            } else {
                putenv('STORAGE_TYPE');
            }

            if ($azureUrlKey !== false) {
                putenv('AZURE_STORAGE_URL=' . $azureUrlKey);
            } else {
                putenv('AZURE_STORAGE_URL');
            }

            if ($azureTokenKey !== false) {
                putenv('AZURE_STORAGE_TOKEN=' . $azureTokenKey);
            } else {
                putenv('AZURE_STORAGE_TOKEN');
            }
        }
    }

    #[Group('unit')]
    public function testCreateStorageThrowsOnUnsupportedType(): void
    {
        $storageKey = getenv('STORAGE_TYPE');
        putenv('STORAGE_TYPE=UNSUPPORTED');

        try {
            $this->expectException(UnsupportedStorageTypeException::class);
            StorageFactory::createStorage();
        } finally {
            if ($storageKey !== false) {
                putenv('STORAGE_TYPE=' . $storageKey);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }
}
