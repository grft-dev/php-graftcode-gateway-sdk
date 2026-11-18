<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\Blob\BlobService;

use InvalidArgumentException;

final class BlobServiceUrlEmptyException extends InvalidArgumentException
{
    public function __construct()
    {
        return parent::__construct('Blob Service Url cannot be empty');
    }
}
