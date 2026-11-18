<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use RuntimeException;
use Throwable;

final class LocalAnalysisFailedException extends RuntimeException
{
    public function __construct(string $message, Throwable $previous)
    {
        return parent::__construct(sprintf('Unexpected error during local analysis: "%s"', $message), previous: $previous);
    }
}
