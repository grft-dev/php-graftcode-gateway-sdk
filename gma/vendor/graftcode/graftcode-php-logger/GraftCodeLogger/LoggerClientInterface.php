<?php

declare(strict_types=1);

namespace GraftCodeLogger;

use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\Logger\Scope\OperationScope;
use GraftCodeLogger\Utils\VO\TraceScope;

interface LoggerClientInterface
{
    public function getLogger(): LoggerInterface;

    public function flush(): void;

    public function getTraceScope(): TraceScope;

    public function getTraceId(): string;

    public function getParentSpanId(): ?string;

    public function startOperation(
        ?string $traceId = null,
        ?string $parentSpanId = null,
        ?string $methodName = null,
        ?string $url = null,
    ): OperationScope;

    public function withOperationScope(?string $traceId, callable $work): mixed;
}
