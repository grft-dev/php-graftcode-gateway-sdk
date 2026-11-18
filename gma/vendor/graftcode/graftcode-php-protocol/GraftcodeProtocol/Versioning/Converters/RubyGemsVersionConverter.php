<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use Exception;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

/**
 * Converts between RubyGems version strings and the canonical PackageVersion format.
 * 
 * RubyGems format examples:
 * - 1.0.0 (stable)
 * - 1.0.0.pre.1 (pre-release)
 * - 1.0.0.alpha.2 (alpha)
 * - 1.0.0.a.1 (short alpha)
 * - 1.0.0.beta.3 (beta)
 * - 1.0.0.b.3 (short beta)
 * - 1.0.0.rc.1 (release candidate)
 * - 1.0.0.snapshot (snapshot without number)
 * - 1.0.0-java (platform-specific)
 * - 1.0.0.pre.1-java (pre-release with platform)
 */
final readonly class RubyGemsVersionConverter implements IVersionConverter
{
    // RubyGems pattern: major.minor.patch[.prerelease][.number][-platform]
    // Accepts any alphanumeric prerelease label
    private const string VERSION_PATTERN = '/^(\d+)\.(\d+)\.(\d+)(?:\.([a-z]+)(?:\.(\d+))?)?(?:-([a-zA-Z0-9_-]+))?$/i';

    public function toCanonical(string $versionString): PackageVersion
    {
        if (empty(trim($versionString))) {
            throw new InvalidArgumentException('Version string cannot be null or empty.');
        }

        if (!preg_match(self::VERSION_PATTERN, $versionString, $matches)) {
            throw new InvalidArgumentException('Version string "'. $versionString .'" does not match RubyGems format.');
        }

        // Groups: [1]major [2]minor [3]patch [4]preLabel [5]preNum [6]platform
        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        $patch = (int)$matches[3];
        
        $prereleaseLabel = isset($matches[4]) ? strtolower($matches[4]) : null;
        $prereleaseNumber = isset($matches[5]) ? (int)$matches[5] : null;
        $platform = $matches[6] ?? null;

        return new PackageVersion(
            $major, $minor, $patch,
            $prereleaseLabel, $prereleaseNumber, null,
            null, null, $platform
        );
    }

    public function tryToCanonical(string $versionString): ParseResult
    {
        try {
            $packageVersion = $this->toCanonical($versionString);

            return ParseResult::successResult($packageVersion);
        } catch (Exception $e) {
            return ParseResult::failureResult($e->getMessage());
        }
    }

    public function fromCanonical(?PackageVersion $canonical): string
    {
        if ($canonical === null) {
            throw new InvalidArgumentException('Canonical version cannot be null.');
        }

        $version = $canonical->major . '.' . $canonical->minor . '.' . $canonical->patch;

        // Add pre-release with dot notation
        if ($canonical->prereleaseLabel !== null) {
            $version .= '.' . strtolower($canonical->prereleaseLabel);
            
            if ($canonical->prereleaseNumber !== null) {
                $version .= '.' . $canonical->prereleaseNumber;
            }
        }

        // Add platform if present
        if ($canonical->platform !== null) {
            $version .= '-' . $canonical->platform;
        }

        return $version;
    }

    public function tryFromCanonical(?PackageVersion $canonical): ParseResult
    {
        if ($canonical === null) {
            return ParseResult::failureResult('Canonical version cannot be null.');
        }
        
        try {
            $versionString = $this->fromCanonical($canonical);

            return ParseResult::successResult($versionString);
        } catch (Exception $e) {
            return ParseResult::failureResult($e->getMessage());
        }
    }
}
