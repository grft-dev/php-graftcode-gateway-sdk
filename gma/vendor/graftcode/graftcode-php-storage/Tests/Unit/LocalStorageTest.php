<?php

declare(strict_types=1);

namespace Unit;

use FilesystemIterator;
use GraftcodeStorage\Storage\LocalStorage;
use GraftcodeStorage\Utils\Exception\{
    CreateDirectoryException,
    File\FileCopyException,
    File\FileDeleteException,
    File\FileNotFoundException
};
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LocalStorageTest extends TestCase
{
    private string $storageBasePath;
    private LocalStorage $storage;

    protected function setUp(): void
    {
        $this->storageBasePath = sys_get_temp_dir() . '/graftcode-local-storage';
        $this->storage = new LocalStorage();
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->storageBasePath);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    /**
     * @throws FileDeleteException
     * @throws CreateDirectoryException
     * @throws FileNotFoundException
     * @throws FileCopyException
     */
    #[Group('unit')]
    public function testFullPathReturnsCorrectPath(): void
    {
        $storageBlobPath = '/test-container/file.txt';
        $expected = $this->storageBasePath . '/php/test-container/file.txt';

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');
        $uploadBlobResponseStatus = $this->storage->uploadBlob($tempFile, $storageBlobPath);

        $this->assertTrue($uploadBlobResponseStatus);
        $this->assertTrue($this->storage->isBlobExists($storageBlobPath));
        $this->assertFileExists($expected);

        unlink($tempFile);
        $deleteBlobResponseStatus = $this->storage->deleteBlob($storageBlobPath);

        $this->assertTrue($deleteBlobResponseStatus);
        $this->assertFileDoesNotExist($expected);
    }

    /**
     * @throws CreateDirectoryException
     * @throws FileCopyException
     */
    #[Group('unit')]
    public function testCheckFileExistsThrowsExceptionForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);

        $storageBlobPath = '/test-container/non-existing-file.txt';
        $downloadPath = tempnam(sys_get_temp_dir(), 'dst');

        try {
            $this->storage->downloadBlob($downloadPath, $storageBlobPath);
        } finally {
            if (file_exists($downloadPath)) {
                unlink($downloadPath);
            }
        }
    }
}
