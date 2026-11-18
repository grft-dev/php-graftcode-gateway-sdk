<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning;

use Exception;
use GraftcodeProtocol\Enums\PackageManager;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\Converters\{
    ComposerVersionConverter,
    IVersionConverter,
    MavenVersionConverter,
    NpmVersionConverter,
    NuGetVersionConverter,
    PythonVersionConverter,
    RubyGemsVersionConverter
};
use InvalidArgumentException;

/**
 * Factory class that provides version conversion between different package managers and the canonical format.
 * The canonical format uses SemVer 2.0 and can be passed between services as a string.
 */
final class VersionConverter
{
    private static array $converters = [];

    private static function initConverters(): void
    {
        if (empty(self::$converters)) {
            self::$converters = [
                PackageManager::NUGET->value => new NuGetVersionConverter(),
                PackageManager::PYTHON->value => new PythonVersionConverter(),
                PackageManager::NPM->value => new NpmVersionConverter(),
                PackageManager::MAVEN->value => new MavenVersionConverter(),
                PackageManager::RUBYGEMS->value => new RubyGemsVersionConverter(),
                PackageManager::CANONICAL->value => new ComposerVersionConverter(),
            ];
        }
    }

    public static function getConverter(PackageManager $packageManager): IVersionConverter
    {
        self::initConverters();

        return self::$converters[$packageManager->value];
    }

    public static function toCanonical(string $versionString, PackageManager $packageManager = PackageManager::CANONICAL): PackageVersion
    {
        return self::getConverter($packageManager)->toCanonical($versionString);
    }

    public static function tryToCanonical(string $versionString, PackageManager $packageManager = PackageManager::CANONICAL): ParseResult
    {
        try {
            return self::getConverter($packageManager)->tryToCanonical($versionString);
        } catch (Exception $e) {
            return ParseResult::failureResult($e->getMessage());
        }
    }

    public static function fromCanonical(?PackageVersion $canonical, PackageManager $packageManager = PackageManager::CANONICAL): string
    {
        return self::getConverter($packageManager)->fromCanonical($canonical);
    }

    public static function tryFromCanonical(PackageVersion $canonical, PackageManager $packageManager = PackageManager::CANONICAL): array
    {
        try {
            return self::getConverter($packageManager)->tryFromCanonical($canonical);
        } catch (Exception $e) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => $e->getMessage()];
        }
    }

    public static function toCanonicalString(?PackageVersion $canonical): string
    {
        if ($canonical === null) {
            throw new InvalidArgumentException('Canonical version cannot be null.');
        }

        return $canonical->__toString();
    }

    public static function fromCanonicalString(string $canonicalString): PackageVersion
    {
        return PackageVersion::parse($canonicalString);
    }

    public static function tryFromCanonicalString(string $canonicalString): ParseResult
    {
        return PackageVersion::tryParse($canonicalString);
    }
}
