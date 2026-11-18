<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception;

use Exception;

final class CreateDirectoryException extends Exception
{
    public function __construct(string $dirPath)
    {
        return parent::__construct('Cannot create directory: ' . $dirPath);
    }
}
