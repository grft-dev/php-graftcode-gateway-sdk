<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\VO;

use GraftCodeLogger\Utils\Uuid;

final readonly class LoggerPayloadTags
{
    public string $operationId;
    public ?string $spanId;
    public ?string $parentId;

    public function __construct(
        Uuid|string $operationId,
        ?string $spanId = null,
        ?string $parentId = null,
    ) {
        $this->operationId = $operationId instanceof Uuid
            ? $operationId->toString()
            : $operationId;
        $this->spanId = self::normalizeOptional($spanId);
        $this->parentId = self::normalizeOptional($parentId);
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    private static function normalizeOptional(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return $value;
    }
}
