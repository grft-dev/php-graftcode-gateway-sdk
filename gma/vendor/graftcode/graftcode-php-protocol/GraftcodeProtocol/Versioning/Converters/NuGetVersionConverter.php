<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

use Exception;
use GraftcodeProtocol\Models\PackageVersion;
use GraftcodeProtocol\Versioning\ParseResult;
use InvalidArgumentException;

readonly class NuGetVersionConverter implements IVersionConverter
{
    public function toCanonical(string $versionString): PackageVersion
    {
        if (empty(trim($versionString))) {
            throw new InvalidArgumentException('Version string cannot be null or empty.');
        }

        $result = PackageVersion::tryParse($versionString);
        if (!$result->success) {
            throw new InvalidArgumentException('Invalid NuGet version format: ' . $result->errorMessage);
        }

        return $result->packageVersion;
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

        return $canonical->__toString();
    }

    public function tryFromCanonical(?PackageVersion $canonical): array
    {
        if ($canonical === null) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => 'Canonical version cannot be null.'];
        }

        try {
            return ['success' => true, 'versionString' => $canonical->__toString(), 'errorMessage' => null];
        } catch (Exception $e) {
            return ['success' => false, 'versionString' => null, 'errorMessage' => $e->getMessage()];
        }
    }
}
