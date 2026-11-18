<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception\File;

use RuntimeException;

final class LocalFileNotFoundException extends RuntimeException
{
    public function __construct(string $localInputPath)
    {
        return parent::__construct(sprintf('Local file "%s" not found', $localInputPath));
    }
}
