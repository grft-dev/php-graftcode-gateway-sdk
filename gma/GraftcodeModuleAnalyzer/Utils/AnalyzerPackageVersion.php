<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

/** @deprecated Use {@see ComponentInfo::getAnalyzerPackageVersion()} */
final class AnalyzerPackageVersion
{
    public static function get(): string
    {
        return ComponentInfo::getAnalyzerPackageVersion();
    }
}
