<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use GraftcodeModuleAnalyzer\Utils\AnalyzedObjectUtils;
use InvalidArgumentException;

final class ExpectedReflectionTypeException extends InvalidArgumentException
{
    public function __construct(mixed $analyzedObject)
    {
        return parent::__construct(sprintf(
            'Expected ReflectionFunction or ReflectionMethod type, got %s',
            AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
        ));
    }
}
