<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use RuntimeException;

final class LocalDependencyFileNotFoundException extends RuntimeException
{
    public function __construct(string $sourcePath)
    {
        return parent::__construct(sprintf('Local dependency file "%s" not found.', $sourcePath));
    }
}
