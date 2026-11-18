<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use InvalidArgumentException;

final class ExpectedStringTypeException extends InvalidArgumentException
{
    public function __construct(mixed $analyzedObject)
    {
        return parent::__construct(sprintf('Expected string type, got %s', get_debug_type($analyzedObject)));
    }
}
