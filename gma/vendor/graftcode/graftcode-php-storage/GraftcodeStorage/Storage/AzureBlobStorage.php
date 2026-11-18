<?php

declare(strict_types=1);

namespace GraftcodeStorage\Storage;

use GraftcodeStorage\Utils\Enum\HttpMethodName;
use GraftcodeStorage\Utils\Enum\HttpStatusCode;
use GraftcodeStorage\Utils\Exception\{
    Blob\BlobExistsCheckException,
    CreateDirectoryException,
    File\DeleteFileException,
    File\DownloadFileException,
    File\FileNotFoundException,
    File\FileSavingException,
    File\UploadFileException,
    StringParameterEmptyException
};
use GraftcodeStorage\Utils\Validator;
use GuzzleHttp\{
    Client,
    Exception\GuzzleException,
    Psr7\Response,
    Psr7\Uri
};
use GraftcodeStorage\Utils\VO\Blob\{
    BlobPath,
    BlobService
};
use Psr\Http\Message\{
    ResponseInterface,
    UriInterface
};
use Throwable;

final readonly class AzureBlobStorage implements StorageInterface
{
    public function __construct(
        private BlobService $blobService,
        private Client $client = new Client()
    ) {}

    /**
     * @throws StringParameterEmptyException
     * @throws FileNotFoundException
     * @throws UploadFileException
     */
    public function uploadBlob(string $srcFilePath, string $storageBlobPath): true
    {
        Validator::validateStringParameter($srcFilePath, 'srcFilePath');
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        if (!file_exists($srcFilePath)) {
            throw new FileNotFoundException($srcFilePath);
        }

        $url = $this->getBlobUrl(new BlobPath($storageBlobPath));

        try {
            $fileData = file_get_contents($srcFilePath);

            if (false === $fileData) {
                throw new FileNotFoundException($srcFilePath);
            }

            $response = $this->sendPutBlobRequest($url, $fileData);

            $this->throwIfUploadFailed($response, $srcFilePath, $url);

        } catch (UploadFileException | FileNotFoundException $e) {
            throw $e;
        } catch (GuzzleException | Throwable $e) {
            throw new UploadFileException(
                $srcFilePath,
                $this->wrapTransportException($e),
                $url,
                $e,
            );
        }

        return true;
    }

    /**
     * @throws StringParameterEmptyException
     * @throws DownloadFileException
     * @throws FileSavingException
     * @throws CreateDirectoryException
     * @throws FileNotFoundException
     */
    public function downloadBlob(string $destinationPath, string $storageBlobPath): true
    {
        Validator::validateStringParameter($destinationPath, 'destinationPath');
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        $url = $this->getBlobUrl(new BlobPath($storageBlobPath));

        try {
            $response = $this->sendGetBlobRequest($url);

            $this->throwIfBlobNotFound($response, $storageBlobPath);
            $this->throwIfDownloadFailed($response, $url);

            $this->ensureDirectoryExists($destinationPath);

            $savedFile = file_put_contents($destinationPath, $response->getBody()->getContents());
            if (false === $savedFile) {
                throw new FileSavingException($destinationPath);
            }

        } catch (DownloadFileException | FileSavingException | CreateDirectoryException | FileNotFoundException $e) {
            throw $e;
        } catch (GuzzleException | Throwable $e) {
            throw new DownloadFileException($this->wrapTransportException($e), $url, $e);
        }

        return true;
    }

    /**
     * @throws StringParameterEmptyException
     * @throws DeleteFileException
     */
    public function deleteBlob(string $storageBlobPath): true
    {
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        $url = $this->getBlobUrl(new BlobPath($storageBlobPath));

        try {
            $response = $this->sendDeleteBlobRequest($url);

            if (HttpStatusCode::isNotFound($response->getStatusCode())) {
                return true;
            }

            $this->throwIfDeleteFailed($response, $url);

        } catch (DeleteFileException $e) {
            throw $e;
        } catch (GuzzleException | Throwable $e) {
            throw new DeleteFileException($this->wrapTransportException($e), $url, $e);
        }

        return true;
    }

    /**
     * @throws StringParameterEmptyException
     * @throws BlobExistsCheckException
     */
    public function isBlobExists(string $storageBlobPath): bool
    {
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        $url = $this->getBlobUrl(new BlobPath($storageBlobPath));

        try {
            $response = $this->sendHeadBlobRequest($url);

            return $this->interpretExistenceStatus($response, $storageBlobPath, $url);
        } catch (BlobExistsCheckException $e) {
            throw $e;
        } catch (GuzzleException | Throwable $e) {
            throw new BlobExistsCheckException(
                $this->wrapTransportException($e),
                $storageBlobPath,
                $url,
                $e,
            );
        }
    }

    /**
     * @throws CreateDirectoryException
     */
    private function ensureDirectoryExists(string $filePath): void
    {
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath) && !mkdir($dirPath, 0755, true)) {
            throw new CreateDirectoryException($dirPath);
        }
    }

    private function getBlobUrl(BlobPath $blobPath): UriInterface
    {
        $baseUrl = rtrim($this->blobService->blobServiceUrl, '/');

        return new Uri(
            sprintf('%s/php/%s?%s',
                $baseUrl,
                $blobPath->path,
                $this->blobService->blobServiceToken
            )
        );
    }

    /**
     * @throws GuzzleException
     */
    private function sendBlobRequest(
        HttpMethodName $method,
        UriInterface $url,
        array $options = [],
    ): ResponseInterface {
        return $this->client->request($method->value, $url, [
            'http_errors' => false,
            ...$options,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function sendGetBlobRequest(UriInterface $url): ResponseInterface
    {
        return $this->sendBlobRequest(HttpMethodName::GET, $url);
    }

    /**
     * @throws GuzzleException
     */
    private function sendHeadBlobRequest(UriInterface $url): ResponseInterface
    {
        return $this->sendBlobRequest(HttpMethodName::HEAD, $url);
    }

    /**
     * @throws GuzzleException
     */
    private function sendDeleteBlobRequest(UriInterface $url): ResponseInterface
    {
        return $this->sendBlobRequest(HttpMethodName::DELETE, $url);
    }

    /**
     * @throws GuzzleException
     */
    private function sendPutBlobRequest(UriInterface $url, string $fileData): ResponseInterface
    {
        return $this->sendBlobRequest(HttpMethodName::PUT, $url, [
            'headers' => [
                'x-ms-blob-type' => 'BlockBlob',
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $fileData,
        ]);
    }

    /**
     * @throws BlobExistsCheckException
     */
    private function interpretExistenceStatus(
        ResponseInterface $response,
        string $storageBlobPath,
        UriInterface $url,
    ): bool {
        $statusCode = $response->getStatusCode();

        if (HttpStatusCode::isNotFound($statusCode)) {
            return false;
        }

        if (HttpStatusCode::isOk($statusCode)) {
            return true;
        }

        throw new BlobExistsCheckException($response, $storageBlobPath, $url);
    }

    /**
     * @throws FileNotFoundException
     */
    private function throwIfBlobNotFound(ResponseInterface $response, string $storageBlobPath): void
    {
        if (HttpStatusCode::isNotFound($response->getStatusCode())) {
            throw new FileNotFoundException($storageBlobPath);
        }
    }

    /**
     * @throws UploadFileException
     */
    private function throwIfUploadFailed(
        ResponseInterface $response,
        string $srcFilePath,
        UriInterface $url,
    ): void {
        if (HttpStatusCode::isNotCreated($response->getStatusCode())) {
            throw new UploadFileException($srcFilePath, $response, $url);
        }
    }

    /**
     * @throws DownloadFileException
     */
    private function throwIfDownloadFailed(ResponseInterface $response, UriInterface $url): void
    {
        if (HttpStatusCode::isNotOk($response->getStatusCode())) {
            throw new DownloadFileException($response, $url);
        }
    }

    /**
     * @throws DeleteFileException
     */
    private function throwIfDeleteFailed(ResponseInterface $response, UriInterface $url): void
    {
        if (HttpStatusCode::isNotAllowed($response->getStatusCode())) {
            throw new DeleteFileException($response, $url);
        }
    }

    private function wrapTransportException(Throwable $e): Response
    {
        return new Response(
            $e->getCode() ?: HttpStatusCode::INTERNAL_SERVER_ERROR->value,
            reason: $e->getMessage()
        );
    }
}
