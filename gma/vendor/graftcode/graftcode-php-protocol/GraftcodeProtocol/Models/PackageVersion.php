<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Models;

use Exception;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

/**
 * Canonical package version model following SemVer 2.0 format with extensions.
 * This model preserves the original format as much as possible for traceability.
 * 
 * Extended fields support:
 * - epoch: Python PEP 440 epoch (e.g., "1!" in "1!2.0.0")
 * - localVersion: Python PEP 440 local version identifier (e.g., "+ubuntu1")
 * - platform: RubyGems platform-specific identifier (e.g., "-java")
 */
final readonly class PackageVersion
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public ?string $prereleaseLabel = null,
        public ?int $prereleaseNumber = null,
        public ?string $buildMetadata = null,
        public ?int $epoch = null,
        public ?string $localVersion = null,
        public ?string $platform = null
    ) {}

    /**
     * Returns the canonical SemVer 2.0 string representation of the version.
     */
    public function __toString(): string
    {
        $version = $this->major . '.' . $this->minor . '.' . $this->patch;
        if ($this->prereleaseLabel !== null) {
            $version .= '-' . $this->prereleaseLabel;
            if ($this->prereleaseNumber !== null) {
                $version .= '.' . $this->prereleaseNumber;
            }
        }

        if ($this->buildMetadata !== null) {
            $version .= '+' .  $this->buildMetadata;
        }

        return $version;
    }

    /**
     * Determines whether this version is a pre-release version.
     */
    public function isPrerelease(): bool
    {
        return $this->prereleaseLabel !== null;
    }

    /**
     * Determines whether this version is a stable release.
     */
    public function isStable(): bool
    {
        return $this->prereleaseLabel === null;
    }

    public function equals(?PackageVersion $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->major === $other->major
            && $this->minor === $other->minor
            && $this->patch === $other->patch
            && $this->prereleaseLabel === $other->prereleaseLabel
            && $this->prereleaseNumber === $other->prereleaseNumber
            && $this->buildMetadata === $other->buildMetadata
            && $this->epoch === $other->epoch
            && $this->localVersion === $other->localVersion
            && $this->platform === $other->platform;
    }

    /**
     * Parses a canonical SemVer 2.0 string into a PackageVersion instance.
     *
     * @throws InvalidArgumentException If the version string is not in a valid format
     */
    public static function parse(string $version): PackageVersion
    {
        if (empty(trim($version))) {
            throw new InvalidArgumentException('Version string cannot be null or empty.');
        }

        $result = self::tryParse($version);
        if (!$result->success) {
            throw new InvalidArgumentException(
                'Invalid version format: '. $version . 'Expected SemVer 2.0 format. Error: ' . $result->errorMessage
            );
        }

        return $result->packageVersion;
    }

    /**
     * Tries to parse a canonical SemVer 2.0 string into a PackageVersion instance.
     */
    public static function tryParse(string $version): ParseResult
    {
        if (empty(trim($version))) {
            return ParseResult::failureResult('Version string cannot be null or empty.');
        }

        try {
            // Split by build metadata first
            $buildMetadataParts = explode('+', $version, 2);
            $mainVersion = $buildMetadataParts[0];
            $buildMetadata = $buildMetadataParts[1] ?? null;

            // Split by prerelease
            $prereleaseParts = explode('-', $mainVersion, 2);
            $coreVersion = $prereleaseParts[0];
            $prereleaseInfo = $prereleaseParts[1] ?? null;

            // Parse core version
            $coreResult = self::tryParseCoreVersion($coreVersion);
            if (!$coreResult['success']) {
                return ParseResult::failureResult($coreResult['error']);
            }

            // Parse prerelease info
            $prereleaseResult = self::parsePrereleaseInfo($prereleaseInfo);

            return ParseResult::successResult(new PackageVersion(
                $coreResult['major'],
                $coreResult['minor'],
                $coreResult['patch'],
                $prereleaseResult['label'],
                $prereleaseResult['number'],
                $buildMetadata
            ));
        } catch (Exception $e) {
            return ParseResult::failureResult($e->getMessage());
        }
    }

    private static function tryParseCoreVersion(string $coreVersion): array
    {
        $versionParts = explode('.', $coreVersion);
        if (count($versionParts) !== 3) {
            return ['success' => false, 'error' => 'Version must have exactly 3 parts (major.minor.patch).'];
        }

        $major = filter_var($versionParts[0], FILTER_VALIDATE_INT);
        $minor = filter_var($versionParts[1], FILTER_VALIDATE_INT);
        $patch = filter_var($versionParts[2], FILTER_VALIDATE_INT);

        if ($major === false || $minor === false || $patch === false || $major < 0 || $minor < 0 || $patch < 0) {
            return ['success' => false, 'error' => 'Version parts must be non-negative integers.'];
        }

        return ['success' => true, 'major' => $major, 'minor' => $minor, 'patch' => $patch];
    }

    private static function parsePrereleaseInfo(?string $prereleaseInfo): array
    {
        if ($prereleaseInfo === null) {
            return ['label' => null, 'number' => null];
        }

        $prereleaseSegments = explode('.', $prereleaseInfo);
        $prereleaseLabel = $prereleaseSegments[0];
        $prereleaseNumber = null;

        if (count($prereleaseSegments) > 1) {
            $lastSegment = end($prereleaseSegments);
            if (is_numeric($lastSegment)) {
                $prereleaseNumber = (int)$lastSegment;

                if (count($prereleaseSegments) > 2) {
                    array_pop($prereleaseSegments);
                    $prereleaseLabel = implode('.', $prereleaseSegments);
                }
            } else {
                $prereleaseLabel = $prereleaseInfo;
            }
        }

        return ['label' => $prereleaseLabel, 'number' => $prereleaseNumber];
    }
}
