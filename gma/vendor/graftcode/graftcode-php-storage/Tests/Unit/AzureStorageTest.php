<?php

declare(strict_types=1);

namespace Unit;

use GraftcodeStorage\{
    Storage\AzureBlobStorage,
    Utils\Exception\Blob\BlobExistsCheckException,
    Utils\Exception\File\DeleteFileException,
    Utils\Exception\File\DownloadFileException,
    Utils\Exception\File\FileNotFoundException,
    Utils\Exception\File\UploadFileException,
    Utils\VO\Blob\BlobService
};
use GuzzleHttp\{
    Client,
    Exception\ConnectException,
    Handler\MockHandler,
    HandlerStack,
    Middleware
};
use GuzzleHttp\Psr7\{
    Request,
    Response
};
use PHPUnit\Framework\{
    Attributes\DataProvider,
    Attributes\Group,
    TestCase
};

final class AzureStorageTest extends TestCase
{
    private const string TEST_AZURE_STORAGE_URL = 'https://test.azure-storage.net/';
    private const string TEST_AZURE_STORAGE_TOKEN = 'test_azure_storage_token';
    private const string STORAGE_BLOB_PATH = '/azure_unit_test/blob.txt';

    #[Group('unit')]
    public function testIsBlobExistsReturnsFalseWhenBlobNotFound(): void
    {
        $azure = $this->createAzureStorage([new Response(404)]);

        $this->assertFalse($azure->isBlobExists(self::STORAGE_BLOB_PATH));
    }

    #[Group('unit')]
    public function testIsBlobExistsReturnsTrueWhenBlobExists(): void
    {
        $azure = $this->createAzureStorage([new Response(200)]);

        $this->assertTrue($azure->isBlobExists(self::STORAGE_BLOB_PATH));
    }

    #[Group('unit')]
    #[DataProvider('httpErrorStatusProvider')]
    public function testIsBlobExistsThrowsOnHttpError(int $statusCode): void
    {
        $azure = $this->createAzureStorage([new Response($statusCode, [], 'Error')]);

        $this->expectException(BlobExistsCheckException::class);
        $this->expectExceptionCode($statusCode);

        $azure->isBlobExists(self::STORAGE_BLOB_PATH);
    }

    #[Group('unit')]
    public function testIsBlobExistsThrowsOnTransportError(): void
    {
        $azure = $this->createAzureStorage([
            $this->createConnectException('HEAD'),
        ]);

        try {
            $azure->isBlobExists(self::STORAGE_BLOB_PATH);
            $this->fail('Expected BlobExistsCheckException was not thrown.');
        } catch (BlobExistsCheckException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    #[Group('unit')]
    public function testDownloadBlobBuildsUrlWithoutDoubleSlashWhenBaseUrlHasTrailingSlash(): void
    {
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], 'blob content')]));
        $handlerStack->push($history);

        $blobService = new BlobService(
            'https://gdstpolcshared01.blob.core.windows.net/',
            self::TEST_AZURE_STORAGE_TOKEN
        );
        $azure = new AzureBlobStorage($blobService, new Client([
            'handler' => $handlerStack,
            'http_errors' => false,
        ]));

        $destinationPath = tempnam(sys_get_temp_dir(), 'azure-download');
        try {
            $azure->downloadBlob($destinationPath, 'packages/sdn-test-simple-car/0.2.4.zip');
        } finally {
            if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }

        $this->assertCount(1, $requestHistory);
        $requestUri = (string) $requestHistory[0]['request']->getUri();
        $this->assertSame(
            'https://gdstpolcshared01.blob.core.windows.net/php/packages/sdn-test-simple-car/0.2.4.zip?'
            . self::TEST_AZURE_STORAGE_TOKEN,
            $requestUri,
        );
    }

    #[Group('unit')]
    public function testUploadBlobSucceedsOnCreated(): void
    {
        $srcFilePath = $this->createTempSourceFile();
        $azure = $this->createAzureStorage([new Response(201)]);

        try {
            $this->assertTrue($azure->uploadBlob($srcFilePath, self::STORAGE_BLOB_PATH));
        } finally {
            unlink($srcFilePath);
        }
    }

    #[Group('unit')]
    #[DataProvider('uploadFailureStatusProvider')]
    public function testUploadBlobThrowsOnUnexpectedHttpStatus(int $statusCode): void
    {
        $srcFilePath = $this->createTempSourceFile();
        $azure = $this->createAzureStorage([new Response($statusCode, [], 'Error')]);

        try {
            $this->expectException(UploadFileException::class);
            $this->expectExceptionCode($statusCode);

            $azure->uploadBlob($srcFilePath, self::STORAGE_BLOB_PATH);
        } finally {
            unlink($srcFilePath);
        }
    }

    #[Group('unit')]
    public function testUploadBlobThrowsOnTransportError(): void
    {
        $srcFilePath = $this->createTempSourceFile();
        $azure = $this->createAzureStorage([
            $this->createConnectException('PUT'),
        ]);

        try {
            try {
                $azure->uploadBlob($srcFilePath, self::STORAGE_BLOB_PATH);
                $this->fail('Expected UploadFileException was not thrown.');
            } catch (UploadFileException $e) {
                $this->assertSame(500, $e->getCode());
                $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
            }
        } finally {
            unlink($srcFilePath);
        }
    }

    #[Group('unit')]
    public function testDownloadBlobSucceedsOnOk(): void
    {
        $destinationPath = tempnam(sys_get_temp_dir(), 'azure-download');
        $azure = $this->createAzureStorage([new Response(200, [], 'file content')]);

        try {
            $this->assertTrue($azure->downloadBlob($destinationPath, self::STORAGE_BLOB_PATH));
            $this->assertSame('file content', file_get_contents($destinationPath));
        } finally {
            if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }
    }

    #[Group('unit')]
    public function testDownloadBlobThrowsFileNotFoundOn404(): void
    {
        $destinationPath = tempnam(sys_get_temp_dir(), 'azure-download');
        $azure = $this->createAzureStorage([new Response(404)]);

        try {
            $this->expectException(FileNotFoundException::class);
            $this->expectExceptionMessage('File not found in path: ' . self::STORAGE_BLOB_PATH);

            $azure->downloadBlob($destinationPath, self::STORAGE_BLOB_PATH);
        } finally {
            if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }
    }

    #[Group('unit')]
    #[DataProvider('httpErrorStatusProvider')]
    public function testDownloadBlobThrowsOnHttpError(int $statusCode): void
    {
        $destinationPath = tempnam(sys_get_temp_dir(), 'azure-download');
        $azure = $this->createAzureStorage([new Response($statusCode, [], 'Error')]);

        try {
            $this->expectException(DownloadFileException::class);
            $this->expectExceptionCode($statusCode);

            $azure->downloadBlob($destinationPath, self::STORAGE_BLOB_PATH);
        } finally {
            if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }
    }

    #[Group('unit')]
    public function testDownloadBlobThrowsOnTransportError(): void
    {
        $destinationPath = tempnam(sys_get_temp_dir(), 'azure-download');
        $azure = $this->createAzureStorage([
            $this->createConnectException('GET'),
        ]);

        try {
            try {
                $azure->downloadBlob($destinationPath, self::STORAGE_BLOB_PATH);
                $this->fail('Expected DownloadFileException was not thrown.');
            } catch (DownloadFileException $e) {
                $this->assertSame(500, $e->getCode());
                $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
            }
        } finally {
            if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }
    }

    #[Group('unit')]
    #[DataProvider('deleteSuccessStatusProvider')]
    public function testDeleteBlobSucceedsOnAllowedStatus(int $statusCode): void
    {
        $azure = $this->createAzureStorage([new Response($statusCode)]);

        $this->assertTrue($azure->deleteBlob(self::STORAGE_BLOB_PATH));
    }

    #[Group('unit')]
    #[DataProvider('httpErrorStatusProvider')]
    public function testDeleteBlobThrowsOnHttpError(int $statusCode): void
    {
        $azure = $this->createAzureStorage([new Response($statusCode, [], 'Error')]);

        $this->expectException(DeleteFileException::class);
        $this->expectExceptionCode($statusCode);

        $azure->deleteBlob(self::STORAGE_BLOB_PATH);
    }

    #[Group('unit')]
    public function testDeleteBlobThrowsOnTransportError(): void
    {
        $azure = $this->createAzureStorage([
            $this->createConnectException('DELETE'),
        ]);

        try {
            $azure->deleteBlob(self::STORAGE_BLOB_PATH);
            $this->fail('Expected DeleteFileException was not thrown.');
        } catch (DeleteFileException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function httpErrorStatusProvider(): iterable
    {
        yield '403 Forbidden' => [403];
        yield '500 Internal Server Error' => [500];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function uploadFailureStatusProvider(): iterable
    {
        yield '200 OK instead of 201 Created' => [200];

        foreach (self::httpErrorStatusProvider() as $name => $case) {
            yield 'upload ' . $name => $case;
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function deleteSuccessStatusProvider(): iterable
    {
        yield '404 Not Found is idempotent' => [404];
        yield '202 Accepted' => [202];
    }

    /**
     * @param array<int, Response|ConnectException> $responses
     */
    private function createAzureStorage(array $responses): AzureBlobStorage
    {
        $blobService = new BlobService(
            self::TEST_AZURE_STORAGE_URL,
            self::TEST_AZURE_STORAGE_TOKEN
        );

        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
            'http_errors' => false,
        ]);

        return new AzureBlobStorage($blobService, $client);
    }

    private function createTempSourceFile(string $content = 'upload content'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'azure-upload');
        file_put_contents($path, $content);

        return $path;
    }

    private function createConnectException(string $method): ConnectException
    {
        return new ConnectException(
            'cURL error 6: Could not resolve host: test.azure-storage.net',
            new Request($method, self::TEST_AZURE_STORAGE_URL),
        );
    }
}
