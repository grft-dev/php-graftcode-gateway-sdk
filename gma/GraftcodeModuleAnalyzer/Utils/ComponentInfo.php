<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use Composer\InstalledVersions;
use GraftcodeModuleAnalyzer\Utils\Exception\CannotLoadComposerJsonException;
use GraftcodeModuleAnalyzer\Utils\Exception\MissingComposerVersionException;

final class ComponentInfo
{
    public static function getAnalyzerPackageVersion(): string
    {
        return self::readAnalyzerPackageVersion();
    }

    public static function getGmaUgmVersion(): string
    {
        return self::getAnalyzerPackageVersion();
    }

    private static function readAnalyzerPackageVersion(): string
    {
        $path = dirname(__DIR__, 2) . '/composer.json';
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new CannotLoadComposerJsonException();
        }

        $name = $data['name'] ?? null;

        if (! is_string($name)) {
            throw new CannotLoadComposerJsonException();
        }

        $version = InstalledVersions::getPrettyVersion($name);

        if (! is_string($version)) {
            throw new MissingComposerVersionException();
        }

        return explode('+', $version, 2)[0];
    }
}
