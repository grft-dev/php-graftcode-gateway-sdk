<?php

declare(strict_types=1);

namespace GraftcodeStorage\Storage;

use GraftcodeStorage\Utils\Exception\{
    CreateDirectoryException,
    File\FileCopyException,
    File\FileDeleteException,
    File\FileNotFoundException,
    StringParameterEmptyException
};
use GraftcodeStorage\Utils\Validator;
use Throwable;

final readonly class LocalStorage implements StorageInterface
{
    private string $baseDirectory;

    public function __construct()
    {
        $this->baseDirectory = sprintf('%s/graftcode-local-storage', sys_get_temp_dir());
    }

    /**
     * @throws StringParameterEmptyException
     * @throws FileNotFoundException
     * @throws FileCopyException
     * @throws CreateDirectoryException
     */
    public function uploadBlob(string $srcFilePath, string $storageBlobPath): true
    {
        Validator::validateStringParameter($srcFilePath, 'srcFilePath');
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        try {
            $destinationFilePath = $this->generateLocalBlobPath($storageBlobPath);

            $this->checkFileExists($srcFilePath);
            $this->ensureDirectoryExists($destinationFilePath);

            if (!copy($srcFilePath, $destinationFilePath)) {
                throw new FileCopyException($srcFilePath, $destinationFilePath);
            }
        } catch (FileCopyException | FileNotFoundException | CreateDirectoryException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileCopyException($srcFilePath, $destinationFilePath , $e);
        }

        return true;
    }

    /**
     * @throws StringParameterEmptyException
     * @throws FileNotFoundException
     * @throws FileCopyException
     * @throws CreateDirectoryException
     */
    public function downloadBlob(string $destinationPath, string $storageBlobPath): true
    {
        Validator::validateStringParameter($destinationPath, 'destinationPath');
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        try {
            $srcPath = $this->generateLocalBlobPath($storageBlobPath);

            $this->checkFileExists($srcPath);
            $this->ensureDirectoryExists($destinationPath);

            if (!copy($srcPath, $destinationPath)) {
                throw new FileCopyException($srcPath, $destinationPath);
            }
        } catch (FileCopyException | FileNotFoundException | CreateDirectoryException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileCopyException($srcPath, $destinationPath, $e);
        }

        return true;
    }

    /**
     * @throws StringParameterEmptyException
     * @throws FileDeleteException
     */
    public function deleteBlob(string $storageBlobPath): true
    {
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        try {
            $filePath = $this->generateLocalBlobPath($storageBlobPath);

            $this->checkFileExists($filePath);

            if (!unlink($filePath)) {
                throw new FileDeleteException($filePath);
            }
        } catch (FileDeleteException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileDeleteException($filePath, $e);
        }

        return true;
    }

    public function isBlobExists(string $storageBlobPath): bool
    {
        Validator::validateStringParameter($storageBlobPath, 'storageBlobPath');

        $filePath = $this->generateLocalBlobPath($storageBlobPath);

        return file_exists($filePath);
    }

    private function generateLocalBlobPath(string $storageBlobPath): string
    {
        return sprintf('%s/php/%s', $this->baseDirectory, $storageBlobPath);
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

    /**
     * @throws FileNotFoundException
     */
    private function checkFileExists(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }
    }
}
