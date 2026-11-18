<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;
use Throwable;

final class FileCopyException extends Exception
{
    public function __construct(?string $srcPath = 'unknown', ?string $destinationPath = 'unknown', ?Throwable $previous = null)
    {
        return parent::__construct(sprintf('Failed copy file from: %s to: %s', $srcPath, $destinationPath), previous: $previous);
    }
}
