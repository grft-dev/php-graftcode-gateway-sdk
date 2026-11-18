<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Enum;

enum OperationOutcome: string
{
    case Success = '200';
    case Failure = '500';

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }
}
