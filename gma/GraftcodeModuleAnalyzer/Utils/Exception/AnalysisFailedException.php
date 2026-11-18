<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use RuntimeException;
use Throwable;

final class AnalysisFailedException extends RuntimeException
{
    public function __construct(string $message, Throwable $previous)
    {
        return parent::__construct(sprintf('Unexpected error during analysis: "%s"', $message), previous: $previous);
    }
}
