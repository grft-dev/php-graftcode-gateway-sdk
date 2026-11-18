<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use RuntimeException;

final class TempDirectoryCreationException extends RuntimeException
{
    public function __construct(string $tempDir)
    {
        return parent::__construct(sprintf('Failed to create temporary directory: %s', $tempDir));
    }
}
