<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils;

use GraftcodeStorage\Utils\Exception\StringParameterEmptyException;

final readonly class Validator
{
    /**
     * @throws StringParameterEmptyException
     */
    public static function validateStringParameter(string $value, string $parameterName): void
    {
        if (empty(trim($value))) {
            throw new StringParameterEmptyException($parameterName);
        }
    }
}
