<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use InvalidArgumentException;

final class SourcePackagePathNotDirectoryException extends InvalidArgumentException
{
    public function __construct(string $sourcePath)
    {
        return parent::__construct(sprintf('Source package path "%s" is not a directory', $sourcePath));
    }
}
