<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Const;

use GraftcodeModuleAnalyzer\Utils\ComponentInfo;

final class Versions
{
    public const string PACKAGE_DEFAULT_VERSION = '0.0.1';

    public static function getGmaUgmVersion(): string
    {
        return ComponentInfo::getAnalyzerPackageVersion();
    }
}
