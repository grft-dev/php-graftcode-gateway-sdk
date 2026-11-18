<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;

final class FileSavingException extends Exception
{
    public function __construct(string $path)
    {
        return parent::__construct(sprintf('File not save in path: %s', $path));
    }
}
