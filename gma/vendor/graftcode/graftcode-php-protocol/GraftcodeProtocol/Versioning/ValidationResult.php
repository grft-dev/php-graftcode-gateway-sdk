<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning;

/**
 * Represents the result of a version validation.
 */
final readonly class ValidationResult
{
    private bool $success;
    private bool $isValid; // Backward compatibility
    private ?string $errorMessage;

    public function __construct(bool $success, ?string $errorMessage = null)
    {
        $this->success = $success;
        $this->isValid = $success; // Backward compatibility
        $this->errorMessage = $errorMessage;
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function __toString(): string
    {
        return $this->success ? 'Valid' : 'Invalid: ' . $this->errorMessage;
    }
}
