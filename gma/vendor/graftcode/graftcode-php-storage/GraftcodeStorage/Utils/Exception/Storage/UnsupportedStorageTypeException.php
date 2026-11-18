<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\Storage;

use Exception;

final class UnsupportedStorageTypeException extends Exception
{
    public function __construct(string $storageType)
    {
        return parent::__construct(sprintf('Unsupported storage type: %s', $storageType));
    }
}
