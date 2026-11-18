<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

interface ModuleAnalyzerInterface
{
    public function analyze(
        string $containerName,
        string $inputBlobName,
        string $traceId = '',
        string $parentTraceId = '',
        array $dependencies = []
    ): array;
}
