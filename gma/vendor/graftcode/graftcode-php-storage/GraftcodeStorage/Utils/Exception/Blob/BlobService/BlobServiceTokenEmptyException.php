<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\Blob\BlobService;

use InvalidArgumentException;

final class BlobServiceTokenEmptyException extends InvalidArgumentException
{
    public function __construct()
    {
        return parent::__construct('Blob Service Token cannot be empty');
    }
}
