<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;
use Throwable;

final class InvokeStaticMethodAnalyzerHandlerException extends Exception
{

    public function __construct(Throwable $e)
    {
        return parent::__construct('Failed to generate UnifiedGraftModel for static method', previous: $e);
    }
}
