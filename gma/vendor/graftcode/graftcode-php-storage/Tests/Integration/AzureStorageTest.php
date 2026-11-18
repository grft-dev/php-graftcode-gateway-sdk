<?php

declare(strict_types=1);

namespace Integration;

use Exception;
use GraftcodeStorage\Storage\AzureBlobStorage;
use GraftcodeStorage\Utils\Exception\{
    CreateDirectoryException,
    File\DownloadFileException,
    File\FileSavingException,
    File\FileNotFoundException
};
use GraftcodeStorage\Utils\VO\Blob\BlobService;
use GuzzleHttp\Client;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use ReflectionClass;
use ReflectionException;

final class AzureStorageTest extends TestCase
{
    private const string TEST_FILE_PATH = '/shared-storage/';
    private const string TEST_FILE_NAME = 'test_integration_azure_php.txt';
    private const string TEST_FULL_FILE_PATH_AND_NAME = self::TEST_FILE_PATH . self::TEST_FILE_NAME;
    private const string TEST_CONTENT = 'This is the test integration azure content';
    private AzureBlobStorage $azure;

    /**
     * @throws ReflectionException
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

        $existingUrlKey = getenv('AZURE_STORAGE_URL');
        if (!$existingUrlKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_URL to run integration tests');
        }

        $existingTokenKey = getenv('AZURE_STORAGE_TOKEN');
        if (!$existingTokenKey) {
            $this->markTestIncomplete('Not configured AZURE_STORAGE_TOKEN to run integration tests');
        }

        $blobService = new BlobService(
            $existingUrlKey,
            $existingTokenKey
        );

        $storage = new AzureBlobStorage($blobService);

        $reflection = new ReflectionClass($storage);
        $newStorage = $reflection->newInstanceWithoutConstructor();

        $blobServiceProperty = $reflection->getProperty('blobService');
        $blobServiceProperty->setValue($newStorage, $blobServiceProperty->getValue($storage));

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($newStorage, new Client(['verify' => false]));

        $this->azure = $newStorage;
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
    public function testFailAzureDeletedNotExistFileName(): void
    {
        $testFilename = '1234.unknownExistentFileNames.pdf';
        $storageBlobPath = '/' . $testFilename;

        $this->assertTrue($this->azure->deleteBlob($storageBlobPath));
    }

    /**
     * @throws DownloadFileException
     * @throws FileSavingException
     * @throws CreateDirectoryException
     */
    #[Group('integration')]
    public function testFailAzureDownloadFileNotExistFileName(): void
    {
        $testFilename = 'noExistFileNames.txt';

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File not found in path: /' . $testFilename);

        $storageBlobPath = '/' . $testFilename;
        $this->azure->downloadBlob(self::TEST_FILE_PATH . $testFilename, $storageBlobPath);
    }

    #[Group('integration')]
    public function testFailAzureExistFileNotExistFileName(): void
    {
        $storageBlobPath = '/nonExistentFile.txt';
        $this->assertFalse(
            $this->azure->isBlobExists($storageBlobPath)
        );
    }

    #[Group('integration')]
    public function testFailAzureExistFileNotExistContainerName(): void
    {
        $storageBlobPath = '/noExistContainerName/' . self::TEST_FILE_NAME;
        $this->assertFalse(
            $this->azure->isBlobExists($storageBlobPath)
        );
    }

    #[Group('integration')]
    public function testAzureUploadFile(): void
    {
        try {
            $storageBlobPath = '/' . self::TEST_FILE_NAME;

            $this->assertFileExists(self::TEST_FULL_FILE_PATH_AND_NAME);
            $this->assertSame(self::TEST_CONTENT, file_get_contents(self::TEST_FULL_FILE_PATH_AND_NAME));
            $this->assertFalse($this->azure->isBlobExists($storageBlobPath));

            $uploadBlobResponseStatus = $this->azure->uploadBlob(self::TEST_FULL_FILE_PATH_AND_NAME, $storageBlobPath);

            $this->assertTrue($uploadBlobResponseStatus);
            $this->assertTrue($this->azure->isBlobExists($storageBlobPath));
        } catch (Exception $e) {
            $this->fail('An exception was not expected, but it was thrown: ' . $e->getMessage());
        }
    }

    #[Group('integration')]
    public function testAzureDownloadFile(): void
    {
        try {
            $storageBlobPath = '/' . self::TEST_FILE_NAME;
            $downloadPath = self::TEST_FILE_PATH . 'downloaded_' . self::TEST_FILE_NAME;

            $this->assertTrue($this->azure->isBlobExists($storageBlobPath));

            $this->azure->downloadBlob($downloadPath, $storageBlobPath);
            $this->assertFileExists($downloadPath);
            $this->assertSame(self::TEST_CONTENT, file_get_contents($downloadPath));

            if (file_exists($downloadPath)) {
                unlink($downloadPath);
            }
        } catch (Exception $e) {
            $this->fail('An exception was not expected, but it was thrown: ' . $e->getMessage());
        }
    }

    #[Group('integration')]
    public function testAzureDeletedFile(): void
    {
        try {
            $storageBlobPath = '/' . self::TEST_FILE_NAME;

            $this->assertTrue($this->azure->isBlobExists($storageBlobPath));

            $deleteBlobResponseStatus = $this->azure->deleteBlob($storageBlobPath);

            $this->assertTrue($deleteBlobResponseStatus);
            $this->assertFalse($this->azure->isBlobExists($storageBlobPath));
        } catch (Exception $e) {
            $this->fail('An exception was not expected, but it was thrown: ' . $e->getMessage());
        }
    }
}
