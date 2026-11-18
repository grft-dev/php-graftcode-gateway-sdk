<?php

declare(strict_types=1);

namespace Integration;

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
    #[Group('integration')]
    public function testUploadAndDownloadFile(): void
    {
        $storageBlobPath = '/integration/blob.txt';
        $testContent = 'test content';
        $testSrcFile = tempnam(sys_get_temp_dir(), 'src');

        if (!file_put_contents($testSrcFile, $testContent)) {
            $this->markTestIncomplete('Not saved testing integration file in ' . $testSrcFile);
        }

        $uploadBlobResponseStatus = $this->storage->uploadBlob($testSrcFile, $storageBlobPath);

        $this->assertTrue($uploadBlobResponseStatus);
        $this->assertTrue($this->storage->isBlobExists($storageBlobPath));

        $downloadPath = tempnam(sys_get_temp_dir(), 'dst');
        $downloadBlobResponseStatus = $this->storage->downloadBlob($downloadPath, $storageBlobPath);

        $this->assertTrue($downloadBlobResponseStatus);
        $this->assertFileExists($downloadPath);
        $this->assertSame($testContent, file_get_contents($downloadPath));

        $deleteBlobResponseStatus = $this->storage->deleteBlob($storageBlobPath);

        $this->assertTrue($deleteBlobResponseStatus);
        $this->assertFalse($this->storage->isBlobExists($storageBlobPath));

        unlink($testSrcFile);
        unlink($downloadPath);
    }
}
