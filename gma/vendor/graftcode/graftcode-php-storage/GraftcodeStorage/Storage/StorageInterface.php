<?php

declare(strict_types=1);

namespace GraftcodeStorage\Storage;

interface StorageInterface
{
    public function uploadBlob(string $srcFilePath, string $storageBlobPath): true;
    public function downloadBlob(string $destinationPath, string $storageBlobPath): true;
    public function deleteBlob(string $storageBlobPath): true;
    public function isBlobExists(string $storageBlobPath): bool;
}
