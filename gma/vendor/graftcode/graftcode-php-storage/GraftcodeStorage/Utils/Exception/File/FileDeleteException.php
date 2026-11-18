<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;
use Throwable;

final class FileDeleteException extends Exception
{
    public function __construct(?string $filePath = 'unknown', ?Throwable $previous = null)
    {
        return parent::__construct(sprintf('Failed delete file from: %s', $filePath), previous: $previous);
    }
}
