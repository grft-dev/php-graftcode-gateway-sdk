<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;

final class FileNotFoundException extends Exception
{
    public function __construct(string $path)
    {
        return parent::__construct(sprintf('File not found in path: %s', $path));
    }
}
