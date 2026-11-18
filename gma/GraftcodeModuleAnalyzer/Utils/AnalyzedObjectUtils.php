<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

final readonly class AnalyzedObjectUtils
{
    public static function getClassNameOrType(mixed $analyzedObject): string
    {
        if (is_object($analyzedObject)) {
            return get_class($analyzedObject);
        }

        return gettype($analyzedObject);
    }
}
