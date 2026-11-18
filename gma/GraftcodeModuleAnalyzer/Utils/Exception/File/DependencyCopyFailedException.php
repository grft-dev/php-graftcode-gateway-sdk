<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use RuntimeException;

final class DependencyCopyFailedException extends RuntimeException
{
    public function __construct(string $sourcePath, string $destinationPath)
    {
        return parent::__construct(sprintf('Failed to copy dependency from "%s" to "%s"', $sourcePath, $destinationPath));
    }
}
