<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\Zip;

use RuntimeException;

final class ZipFileOpenException extends RuntimeException
{
    public function __construct(string $zipPath)
    {
        return parent::__construct(sprintf('Cannot open ZIP file "%s"', $zipPath));
    }
}
