<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use Exception;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

/**
 * Converter for Composer (PHP) version format.
 * Composer uses SemVer 2.0, similar to npm and NuGet.
 * Examples: 1.2.3, 1.2.3-beta.1, 1.2.3-alpha+build.123
 */
final readonly class ComposerVersionConverter implements IVersionConverter
{
    public function toCanonical(string $versionString): PackageVersion
    {
        // Composer uses SemVer 2.0, so we can use PackageVersion::parse directly
        return PackageVersion::parse($versionString);
    }

    public function tryToCanonical(string $versionString): ParseResult
    {
        return PackageVersion::tryParse($versionString);
    }

    public function fromCanonical(?PackageVersion $canonical): string
    {
        if ($canonical === null) {
            throw new InvalidArgumentException('Canonical version cannot be null.');
        }

        $result = $this->tryFromCanonical($canonical);
        if (!$result['success']) {
            throw new InvalidArgumentException("Conversion failed: {$result['errorMessage']}");
        }

        return $result['versionString'];
    }

    public function tryFromCanonical(?PackageVersion $canonical): array
    {
        if ($canonical === null) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => 'Canonical version cannot be null.'];
        }

        try {
            // Composer uses SemVer 2.0, so we can use toString directly
            $versionString = $canonical->__toString();

            return ['success' => true, 'versionString' => $versionString, 'errorMessage' => null];
        } catch (Exception $e) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => $e->getMessage()];
        }
    }
}
