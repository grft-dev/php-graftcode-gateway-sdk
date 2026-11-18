<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning;

use GraftcodeProtocol\Versioning\Converters\ComposerVersionConverter;

/**
 * Validates Composer version strings according to SemVer 2.0 specification.
 * This class is now a wrapper around ComposerVersionConverter for backward compatibility.
 * Consider using PackageVersion::tryParse() or VersionConverter directly for new code.
 */
final class VersionValidator
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
     * Determines whether the specified version string is a valid Composer version.
     *
     * @param string $version The version string to validate
     * @return bool
     */
    public static function isValid(string $version): bool
    {
        $result = self::getConverter()->tryToCanonical($version);

        return $result->success;
    }

    /**
     * Validates a version string and returns a detailed validation result.
     *
     * @param string $version The version string to validate
     * @return ValidationResult
     */
    public static function validate(string $version): ValidationResult
    {
        if (empty(trim($version))) {
            return new ValidationResult(false, 'Version string cannot be null or empty.');
        }

        $result = self::getConverter()->tryToCanonical($version);

        return new ValidationResult($result->success, $result->errorMessage);
    }
}
