<?php

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use InvalidArgumentException;

final class SourceFilePathNotFoundException extends InvalidArgumentException
{
    public function __construct(string $sourcePath)
    {
        return parent::__construct(sprintf('Path does not exist: %s', $sourcePath));
    }
}
