<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use Exception;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

/**
 * Converts between Maven version strings and the canonical PackageVersion format.
 * 
 * Supports:
 * - Basic: 1.0.0
 * - Qualifiers: 1.0.0-beta, 1.0.0-alpha-1, 1.0.0-SNAPSHOT
 * - Multi-dash qualifiers: 1.0.0-rc-1, 1.0.0-milestone-3
 * - Timestamp snapshots: 1.0.0-20210101.123456-1
 * - Numeric-only qualifiers: 1.0.0-1
 */
final readonly class MavenVersionConverter implements IVersionConverter
{
    private const string SNAPSHOT_LABEL = 'SNAPSHOT';
    // Timestamp pattern: 20210101.123456-1
    private const string TIMESTAMP_PATTERN = '/^\d{8}\.\d{6}-\d+$/';

    public function toCanonical(string $versionString): PackageVersion
    {
        $result = $this->tryToCanonical($versionString);
        if (!$result->success) {
            throw new InvalidArgumentException('Invalid Maven version format: ' . $result->errorMessage);
        }
        return $result->packageVersion;
    }

    public function tryToCanonical(string $versionString): ParseResult
    {
        if (empty(trim($versionString))) {
            return ParseResult::failureResult('Version string cannot be null or empty.');
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([A-Za-z0-9.-]+))?$/i', $versionString, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) $matches[3];
            $prereleaseLabel = null;
            $prereleaseNumber = null;
            $buildMetadata = null;

            if (isset($matches[4]) && $matches[4] !== '') {
                $qualifier = $matches[4];
                
                // Check if it's a timestamp snapshot (e.g., 20210101.123456-1)
                if (preg_match(self::TIMESTAMP_PATTERN, $qualifier)) {
                    $prereleaseLabel = strtolower(self::SNAPSHOT_LABEL);
                    $buildMetadata = $qualifier; // Store timestamp as build metadata
                }
                // Check if it's numeric only (e.g., "1")
                elseif (is_numeric($qualifier)) {
                    $prereleaseNumber = (int)$qualifier;
                }
                // Handle multi-dash qualifiers (e.g., "rc-1", "milestone-3")
                elseif (str_contains($qualifier, '-')) {
                    $parts = explode('-', $qualifier, 2);
                    $prereleaseLabel = strtolower($parts[0]);
                    
                    // Check if the last part after dash is numeric
                    if (is_numeric($parts[1])) {
                        $prereleaseNumber = (int)$parts[1];
                    } else {
                        // Replace dashes with dots for canonical format
                        $prereleaseLabel = strtolower(str_replace('-', '.', $qualifier));
                    }
                }
                else {
                    $prereleaseLabel = strtolower($qualifier);
                }
            }

            return ParseResult::successResult(new PackageVersion($major, $minor, $patch, $prereleaseLabel, $prereleaseNumber, $buildMetadata));
        }

        return ParseResult::failureResult('Version string ' . $versionString . ' does not match Maven format.');
    }

    public function fromCanonical(?PackageVersion $canonical): string
    {
        if ($canonical === null) {
            throw new InvalidArgumentException('Canonical version cannot be null.');
        }

        $version = $canonical->major . '.' . $canonical->minor . '.' . $canonical->patch;

        if ($canonical->prereleaseLabel !== null) {
            $mavenLabel = strtolower($canonical->prereleaseLabel);
            
            // Check if it's SNAPSHOT with timestamp build metadata
            if (strcasecmp($mavenLabel, self::SNAPSHOT_LABEL) === 0 && $canonical->buildMetadata !== null) {
                // Reconstruct as timestamp snapshot
                $version .= '-' . $canonical->buildMetadata;
            } else {
                // Replace dots with dashes for Maven format
                $mavenLabel = str_replace('.', '-', $mavenLabel);
                
                $version .= '-' . $mavenLabel;

                // Add prerelease number with dash separator (e.g., alpha-1)
                if ($canonical->prereleaseNumber !== null) {
                    $version .= '-'. $canonical->prereleaseNumber;
                }
            }
        } elseif ($canonical->prereleaseNumber !== null) {
            // Numeric-only qualifier
            $version .= '-' . $canonical->prereleaseNumber;
        }

        return $version;
    }

    public function tryFromCanonical(?PackageVersion $canonical): array
    {
        if ($canonical === null) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => 'Canonical version cannot be null.'];
        }

        try {
            return ['success' => true, 'versionString' => $this->fromCanonical($canonical), 'errorMessage' => null];
        } catch (Exception $e) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => $e->getMessage()];
        }
    }
}

