<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Models;

use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;

final readonly class UnifiedGraftModel implements UnifiedGraftModelInterface
{
    private array $payload;

    public function __construct(
        private RuntimeName $runtimeName,
        private UnifiedGraftModelType $unifiedGraftModelType,
        ...$payload
    ) {
        $this->payload = count($payload) === 1 && is_array($payload[0])
            ? $payload[0]
            : $payload;
    }

    public function addArgToPayload(mixed $arg): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel($this->runtimeName, $this->unifiedGraftModelType, array_merge($this->payload, [$arg]));
    }

    public function getRuntimeName(): RuntimeName
    {
        return $this->runtimeName;
    }

    public function getUnifiedGraftModelType(): UnifiedGraftModelType
    {
        return $this->unifiedGraftModelType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPayloadByIndex(int $index): mixed
    {
        return $this->payload[$index];
    }

    public function findPayloadByIndex(int $index): mixed
    {
        return $this->payload[$index] ?? null;
    }

    public function getPayloadSize(): int
    {
        return count($this->payload);
    }
}
