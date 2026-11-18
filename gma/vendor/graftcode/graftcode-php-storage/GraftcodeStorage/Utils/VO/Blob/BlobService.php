<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\VO\Blob;

use GraftcodeStorage\Utils\Exception\Blob\BlobService\{
    BlobServiceTokenEmptyException,
    BlobServiceUrlEmptyException
};

final readonly class BlobService
{
    public function __construct(
        public string $blobServiceUrl,
        public string $blobServiceToken
    ) {
        if (empty(trim($blobServiceUrl))) {
            throw new BlobServiceUrlEmptyException();
        }

        if (empty(trim($blobServiceToken))) {
            throw new BlobServiceTokenEmptyException();
        }
    }
}
