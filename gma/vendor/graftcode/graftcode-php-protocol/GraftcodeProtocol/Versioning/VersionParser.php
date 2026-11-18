<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning;

use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\Converters\ComposerVersionConverter;
use InvalidArgumentException;

/**
 * Parses Composer version strings according to SemVer 2.0 specification.
 * This class is now a wrapper around ComposerVersionConverter for backward compatibility.
 * Consider using PackageVersion::parse() or VersionConverter directly for new code.
 */
final class VersionParser
{
    private static ?ComposerVersionConverter $converter = null;

    private static function getConverter(): ComposerVersionConverter
    {
        if (self::$converter === null) {
            self::$converter = new ComposerVersionConverter();
        }

        return self::$converter;
    }

    /**
     * Parses a Composer version string into a PackageVersion object.
     *
     * @param string $version The version string to parse
     * @return PackageVersion
     * @throws InvalidArgumentException if the version string is invalid
     */
    public static function parse(string $version): PackageVersion
    {
        return self::getConverter()->toCanonical($version);
    }

    /**
     * Tries to parse a Composer version string into a PackageVersion object.
     *
     * @param string $version The version string to parse
     * @return array{0: PackageVersion|null, 1: string|null} Tuple of (PackageVersion, error_message)
     */
    public static function tryParse(string $version): array
    {
        $result = self::getConverter()->tryToCanonical($version);

        return [$result->packageVersion, $result->errorMessage];
    }
}
