<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\VO\Blob;

use GraftcodeStorage\Utils\Exception\Blob\BlobPath\BlobPathEmptyException;

final readonly class BlobPath
{
    public function __construct(
        public string $path
    ) {
        if (empty(trim($path))) {
            throw new BlobPathEmptyException();
        }
    }
}
