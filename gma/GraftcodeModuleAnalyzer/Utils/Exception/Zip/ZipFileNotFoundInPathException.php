<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\Zip;

use RuntimeException;

final class ZipFileNotFoundInPathException extends RuntimeException
{
    public function __construct(string $zipPath)
    {
        return parent::__construct(sprintf('ZIP file "%s" not found', $zipPath));
    }
}
