<?php

namespace GraftcodeStorage\Utils\Exception;

use InvalidArgumentException;

final class StringParameterEmptyException extends InvalidArgumentException
{
    public function __construct(string $parameterName)
    {
        return parent::__construct(sprintf('Parameter "%s" cannot be empty', $parameterName));
    }
}
