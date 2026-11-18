<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use Exception;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

/**
 * Converts between Python (PEP 440) version strings and the canonical PackageVersion format.
 * 
 * Supports:
 * - Basic: 1.0.0
 * - Epochs: 1!2.0.0
 * - Pre-releases: 1.0.0a1, 1.0.0.alpha.1, 1.0.0b2, 1.0.0rc3
 * - Post-releases: 1.0.0.post1
 * - Dev releases: 1.0.0.dev1
 * - Combined: 1.0.0.post1.dev1
 * - Local versions: 1.0.0+ubuntu1
 */
final readonly class PythonVersionConverter implements IVersionConverter
{
    // Enhanced PEP 440 pattern supporting all cases
    // Format: [epoch!]major.minor.patch[prerelease][.postN][.devN][+local]
    private const string VERSION_PATTERN = '/^(?:(\d+)!)?(\d+)\.(\d+)\.(\d+)(?:\.?(a|alpha|b|beta|rc)\.?(\d+))?(?:\.?(post)\.?(\d+))?(?:\.?(dev)\.?(\d+))?(?:\+([a-zA-Z0-9.]+))?$/i';

    // Note: We preserve the original label format in canonical representation
    // "a" stays "a", "alpha" stays "alpha", "b" stays "b", "beta" stays "beta"

    public function toCanonical(string $versionString): PackageVersion
    {
        $result = $this->tryToCanonical($versionString);
        if (!$result->success) {
            throw new InvalidArgumentException('Invalid Python version format: ' . $result->errorMessage);
        }
        return $result->packageVersion;
    }

    public function tryToCanonical(string $versionString): ParseResult
    {
        if (empty(trim($versionString))) {
            return ParseResult::failureResult('Version string cannot be null or empty.');
        }

        if (preg_match(self::VERSION_PATTERN, $versionString, $matches)) {
            // Groups: [1]epoch [2]major [3]minor [4]patch [5]preLabel [6]preNum [7]post [8]postNum [9]dev [10]devNum [11]local
            $epoch = isset($matches[1]) && $matches[1] !== '' ? (int)$matches[1] : null;
            $major = (int)$matches[2];
            $minor = (int)$matches[3];
            $patch = (int)$matches[4];
            
            // Handle prerelease (a, alpha, b, beta, rc)
            $prereleaseLabel = isset($matches[5]) && $matches[5] !== '' ? strtolower($matches[5]) : null;
            $prereleaseNumber = isset($matches[6]) && $matches[6] !== '' ? (int)$matches[6] : null;

            // Handle dev releases
            // If dev is standalone (no post), treat as prerelease for backward compatibility
            // If combined with post, store in build_metadata
            $hasDev = isset($matches[9]) && $matches[9] !== '';
            $hasPost = isset($matches[7]) && $matches[7] !== '';
            
            $buildMetadata = null;
            if ($hasDev && !$hasPost && !$prereleaseLabel) {
                // Standalone dev release: treat as prerelease
                $prereleaseLabel = 'dev';
                $prereleaseNumber = isset($matches[10]) && $matches[10] !== '' ? (int)$matches[10] : null;
            } else {
                // Build metadata for post releases and combined post+dev
                $buildParts = [];
                
                if ($hasPost) {
                    $postNumber = isset($matches[8]) && $matches[8] !== '' ? $matches[8] : null;
                    $buildParts[] = $postNumber ? 'post.' . $postNumber : 'post';
                }
                
                if ($hasDev && ($hasPost || $prereleaseLabel)) {
                    // dev combined with other releases
                    $devNumber = isset($matches[10]) && $matches[10] !== '' ? $matches[10] : null;
                    $buildParts[] = $devNumber ? 'dev. ' . $devNumber : 'dev';
                }
                
                $buildMetadata = !empty($buildParts) ? implode('.', $buildParts) : null;
            }
            
            $localVersion = isset($matches[11]) && $matches[11] !== '' ? $matches[11] : null;

            return ParseResult::successResult(new PackageVersion(
                $major, $minor, $patch,
                $prereleaseLabel, $prereleaseNumber, $buildMetadata,
                $epoch, $localVersion
            ));
        }

        return ParseResult::failureResult('Version string "' . $versionString . '" does not match Python PEP 440 format.');
    }

    public function fromCanonical(?PackageVersion $canonical): string
    {
        if ($canonical === null) {
            throw new InvalidArgumentException('Canonical version cannot be null.');
        }

        $version = '';
        
        // Add epoch if present
        if ($canonical->epoch !== null) {
            $version .= $canonical->epoch . '!';
        }
        
        $version .= $canonical->major . '.' . $canonical->minor . '.' . $canonical->patch;

        if ($canonical->prereleaseLabel !== null) {
            $label = strtolower($canonical->prereleaseLabel);
            // dev releases use dot notation, others don't
            if ($label === 'dev') {
                $version .= '.' . $label;
            } else {
                $version .= $label;
            }

            if ($canonical->prereleaseNumber !== null) {
                $version .= $canonical->prereleaseNumber;
            }
        }

        // Handle post and dev releases from build metadata
        if ($canonical->buildMetadata !== null) {
            $parts = explode('.', $canonical->buildMetadata);
            $i = 0;
            while ($i < count($parts)) {
                if ($parts[$i] === 'post' && isset($parts[$i + 1])) {
                    $version .= ".post{$parts[$i + 1]}";
                    $i += 2;
                } elseif ($parts[$i] === 'post') {
                    $version .= '.post';
                    $i++;
                } elseif ($parts[$i] === 'dev' && isset($parts[$i + 1])) {
                    $version .= ".dev{$parts[$i + 1]}";
                    $i += 2;
                } elseif ($parts[$i] === 'dev') {
                    $version .= '.dev';
                    $i++;
                } else {
                    $i++;
                }
            }
        }
        
        // Add local version if present
        if ($canonical->localVersion !== null) {
            $version .= '+' . $canonical->localVersion;
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
