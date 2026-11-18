<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Models;

use InvalidArgumentException;

/**
 * Request contract for local package analysis (gg / GPPE type filters).
 */
final readonly class AnalyzeLocalRequest
{
    public function __construct(
        public string $sourcePath,
        public string $typeFilter = '',
        public string $methodFilter = '',
    ) {
        if ($sourcePath === '') {
            throw new InvalidArgumentException('sourcePath is required');
        }
    }
}
