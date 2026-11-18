<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\VO;

use GraftCodeLogger\Utils\Uuid;


final class TraceScope
{
    private string $currentTraceId;
    private ?string $currentParentSpanId = null;

    public function __construct(?string $traceId = null)
    {
        $this->currentTraceId = self::normalize($traceId);
    }

    public function set(?string $traceId): void
    {
        $this->currentTraceId = self::normalize($traceId);
    }

    public function get(): string
    {
        return $this->currentTraceId;
    }

    public function setParentSpanId(?string $spanId): void
    {
        $this->currentParentSpanId = ($spanId === null || trim($spanId) === '')
            ? null
            : $spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->currentParentSpanId;
    }

    private static function normalize(?string $traceId): string
    {
        if ($traceId === null || trim($traceId) === '') {
            return Uuid::uuid4()->toString();
        }
        return $traceId;
    }
}
