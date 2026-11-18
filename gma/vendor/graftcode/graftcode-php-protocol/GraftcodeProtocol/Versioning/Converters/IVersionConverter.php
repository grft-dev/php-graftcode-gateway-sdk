<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;

/**
 * Interface for platform-specific version converters.
 */
interface IVersionConverter
{
    /**
     * Converts a platform-specific version string to the canonical PackageVersion format.
     */
    public function toCanonical(string $versionString): PackageVersion;

    /**
     * Tries to convert a platform-specific version string to the canonical PackageVersion format.
     */
    public function tryToCanonical(string $versionString): ParseResult;

    /**
     * Converts a canonical PackageVersion to a platform-specific version string.
     */
    public function fromCanonical(?PackageVersion $canonical): string;

    /**
     * Tries to convert a canonical PackageVersion to a platform-specific version string.
     * Returns: ['success' => bool, 'versionString' => ?string, 'errorMessage' => ?string]
     */
    public function tryFromCanonical(?PackageVersion $canonical): ParseResult | array;
}
