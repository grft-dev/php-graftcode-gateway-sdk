<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning;

use GraftcodeProtocol\Models\PackageVersion;

/**
 * Represents the result of a version parsing operation.
 */
final readonly class ParseResult
{
    private function __construct(
        public bool $success,
        public null |string | PackageVersion $packageVersion = null,
        public ?string $errorMessage = null
    ) {}

    public static function successResult(string | PackageVersion $packageVersion): ParseResult
    {
        return new self(true, packageVersion: $packageVersion);
    }

    public static function failureResult(string $errorMessage): ParseResult
    {
        return new self(false, errorMessage: $errorMessage);
    }
}
