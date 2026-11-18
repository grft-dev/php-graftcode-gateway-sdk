<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;
use Throwable;

final class InvokeInstanceMethodAnalyzerHandlerException extends Exception
{

    public function __construct(Throwable $e)
    {
        return parent::__construct('Failed to generate UnifiedGraftModel for instance method', previous: $e);
    }
}
