<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Models;

use InvalidArgumentException;

/**
 * Request contract for blob-hosted package analysis.
 */
final readonly class AnalyzePackageRequest
{
    /**
     * @param array<int, string> $dependencies
     */
    public function __construct(
        public string $containerName,
        public string $inputBlobName,
        public string $traceId = '',
        public string $parentTraceId = '',
        public array $dependencies = [],
    ) {
        if ($containerName === '') {
            throw new InvalidArgumentException('containerName is required');
        }

        if ($inputBlobName === '') {
            throw new InvalidArgumentException('inputBlobName is required');
        }
    }
}
