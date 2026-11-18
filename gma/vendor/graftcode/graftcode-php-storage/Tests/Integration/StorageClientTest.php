<?php

declare(strict_types=1);

namespace Integration;

use Exception;
use GraftcodeStorage\StorageClient;
use GraftcodeStorage\Utils\Exception\File\{
    FileNotFoundException,
    FileSavingException
};
use GuzzleHttp\Client;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use ReflectionClass;
use ReflectionException;

final class StorageClientTest extends TestCase
{
    private const string TEST_FILE_PATH = '/shared-storage/';
    private const string TEST_FILE_NAME = 'test_integration_storage_php.txt';
    private const string TEST_FULL_FILE_PATH_AND_NAME = self::TEST_FILE_PATH . self::TEST_FILE_NAME;
    private const string TEST_CONTENT = 'This is the test integration azure content';

    /**
     * @throws FileSavingException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!is_dir(self::TEST_FILE_PATH) && !mkdir(self::TEST_FILE_PATH, 0644, true)) {
            throw new Exception('Failed to create test directory: ' . self::TEST_FILE_PATH);
        }

        $savedFile = file_put_contents(self::TEST_FULL_FILE_PATH_AND_NAME, self::TEST_CONTENT);
        if (false === $savedFile) {
            throw new FileSavingException(self::TEST_FULL_FILE_PATH_AND_NAME);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists(self::TEST_FULL_FILE_PATH_AND_NAME)) {
            unlink(self::TEST_FULL_FILE_PATH_AND_NAME);
        }

        if (is_dir(self::TEST_FILE_PATH) && count(scandir(self::TEST_FILE_PATH)) === 2) {
            rmdir(self::TEST_FILE_PATH);
        }

        parent::tearDown();
    }

    #[Group('integration')]
    public function testFailAzureWhenEnvAzureStorageUrlIsNotExist(): void
    {
        $existingUrlKey = getenv('AZURE_STORAGE_URL');
        $existingTokenKey = getenv('AZURE_STORAGE_TOKEN');
        $existingStorageType = getenv('STORAGE_TYPE');

        putenv('AZURE_STORAGE_URL');
        putenv('AZURE_STORAGE_TOKEN');
        putenv('STORAGE_TYPE=AZURE');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AZURE_STORAGE_URL environment variable is not set');
        try {
            new StorageClient();
        } finally {
            if ($existingUrlKey !== false) {
                putenv('AZURE_STORAGE_URL=' . $existingUrlKey);
            } else {
                putenv('AZURE_STORAGE_URL');
            }
            if ($existingTokenKey !== false) {
                putenv('AZURE_STORAGE_TOKEN=' . $existingTokenKey);
            } else {
                putenv('AZURE_STORAGE_TOKEN');
            }
            if ($existingStorageType !== false) {
                putenv('STORAGE_TYPE=' . $existingStorageType);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }

    #[Group('integration')]
    public function testAzureUploadDownloadDeleteFile(): void
    {
        $existingUrlKey = getenv('AZURE_STORAGE_URL');
        if (!$existingUrlKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_URL to run integration tests');
        }

        $existingTokenKey = getenv('AZURE_STORAGE_TOKEN');
        if (!$existingTokenKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_TOKEN to run integration tests');
        }

        $existStorageKey = getenv('STORAGE_TYPE');
        if (!$existStorageKey) {
            putenv('STORAGE_TYPE=AZURE');
        }

        try {
            $storage = new StorageClient()->getStorage();

            $reflection = new ReflectionClass($storage);
            $newStorage = $reflection->newInstanceWithoutConstructor();

            $blobServiceProperty = $reflection->getProperty('blobService');
            $blobServiceProperty->setValue($newStorage, $blobServiceProperty->getValue($storage));
            
            $clientProperty = $reflection->getProperty('client');
            $clientProperty->setValue($newStorage, new Client(['verify' => false]));

            $storage = $newStorage;

            $this->assertSame(self::TEST_CONTENT, file_get_contents(self::TEST_FULL_FILE_PATH_AND_NAME));

            $storageBlobPath = sprintf('/%s', self::TEST_FILE_NAME);

            $this->assertFalse($storage->isBlobExists($storageBlobPath));

            $uploadBlobResponseStatus = $storage->uploadBlob(self::TEST_FULL_FILE_PATH_AND_NAME, $storageBlobPath);

            $this->assertTrue($uploadBlobResponseStatus);
            $this->assertTrue($storage->isBlobExists($storageBlobPath));

            $downloadPath = sprintf('%sdownloaded_%s', self::TEST_FILE_PATH, self::TEST_FILE_NAME);
            $downloadBlobResponseStatus = $storage->downloadBlob($downloadPath, $storageBlobPath);

            $this->assertTrue($downloadBlobResponseStatus);
            $this->assertFileExists($downloadPath);
            $this->assertSame(file_get_contents(self::TEST_FULL_FILE_PATH_AND_NAME), file_get_contents($downloadPath));

            $this->assertTrue($storage->isBlobExists($storageBlobPath));

            $deleteBlobResponseStatus = $storage->deleteBlob($storageBlobPath);

            $this->assertTrue($deleteBlobResponseStatus);
            $this->assertFalse($storage->isBlobExists($storageBlobPath));

            unlink($downloadPath);
        } catch (ReflectionException $e) {
            var_dump($e->getMessage());
        } finally {
            if ($existStorageKey !== false) {
                putenv('STORAGE_TYPE=' . $existStorageKey);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }

    #[Group('integration')]
    public function testAzureUploadNonExistingFileThrows(): void
    {
        $existingUrlKey = getenv('AZURE_STORAGE_URL');
        if (!$existingUrlKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_URL to run integration tests');
        }

        $existingTokenKey = getenv('AZURE_STORAGE_TOKEN');
        if (!$existingTokenKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_TOKEN to run integration tests');
        }

        $existStorageKey = getenv('STORAGE_TYPE');
        if (!$existStorageKey) {
            putenv('STORAGE_TYPE=AZURE');
        }

        try {
            $this->expectException(FileNotFoundException::class);

            $client = new StorageClient();
            $storage = $client->getStorage();
            $storageBlobPath = '/not_exists.txt';
            $storage->uploadBlob(self::TEST_FILE_PATH . 'not_exists.txt', $storageBlobPath);
        } finally {
            if ($existStorageKey !== false) {
                putenv('STORAGE_TYPE=' . $existStorageKey);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }

    #[Group('integration')]
    public function testLocalUploadNonExistingFileThrows(): void
    {
        $existStorageKey = getenv('STORAGE_TYPE');
        putenv('STORAGE_TYPE=LOCAL');

        try {
            $this->expectException(FileNotFoundException::class);

            $client = new StorageClient();
            $storage = $client->getStorage();
            $storageBlobPath = '/not_exists.txt';
            $storage->uploadBlob(self::TEST_FILE_PATH . 'not_exists.txt', $storageBlobPath);
        } finally {
            if ($existStorageKey !== false) {
                putenv('STORAGE_TYPE=' . $existStorageKey);
            } else {
                putenv('STORAGE_TYPE');
            }
        }
    }
}
