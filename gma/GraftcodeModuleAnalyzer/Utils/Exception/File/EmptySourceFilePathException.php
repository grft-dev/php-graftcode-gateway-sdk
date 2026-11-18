<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use InvalidArgumentException;

final class EmptySourceFilePathException extends InvalidArgumentException
{
    public function __construct()
    {
        return parent::__construct('Source path cannot be empty.');
    }
}
