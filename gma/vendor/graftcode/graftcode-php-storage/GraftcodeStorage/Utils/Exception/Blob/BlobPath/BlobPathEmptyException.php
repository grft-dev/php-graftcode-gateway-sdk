<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\Blob\BlobPath;

use InvalidArgumentException;

final class BlobPathEmptyException extends InvalidArgumentException
{
    public function __construct()
    {
        return parent::__construct('Blob path cannot be empty.');
    }
}
