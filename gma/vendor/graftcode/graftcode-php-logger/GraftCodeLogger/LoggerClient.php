<?php

declare(strict_types=1);

namespace GraftCodeLogger;

use GraftCodeLogger\Logger\Factory\LoggerFactory;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\Logger\Scope\OperationScope;
use GraftCodeLogger\Telemetry\TelemetryChannel;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GraftCodeLogger\Utils\VO\TraceScope;

final class LoggerClient implements LoggerClientInterface
{
    public const string TRACE_ID_ENV_VAR = 'TRACE_ID';
    public const string PARENT_SPAN_ID_ENV_VAR = 'PARENT_SPAN_ID';

    private readonly LoggerInterface $logger;
    private readonly TraceScope $scope;

    public function __construct(
        LoggerClientOptions $config,
        ?TraceScope $scope = null,
    ) {
        if ($scope === null) {
            $scope = new TraceScope(self::extractInboundTraceId());
            $scope->setParentSpanId(self::extractInboundParentSpanId());
        }
        $this->scope = $scope;
        $this->logger = LoggerFactory::createLogger($config, $this->scope);
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function flush(): void
    {
        TelemetryChannel::instance()->flush();
    }

    public function getTraceScope(): TraceScope
    {
        return $this->scope;
    }

    public function getTraceId(): string
    {
        return $this->scope->get();
    }

    public function getParentSpanId(): ?string
    {
        return $this->scope->getParentSpanId();
    }

    private static function extractInboundTraceId(): ?string
    {
        $value = getenv(self::TRACE_ID_ENV_VAR);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return $value;
    }

    private static function extractInboundParentSpanId(): ?string
    {
        $value = getenv(self::PARENT_SPAN_ID_ENV_VAR);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return $value;
    }

    public function startOperation(
        ?string $traceId = null,
        ?string $parentSpanId = null,
        ?string $methodName = null,
        ?string $url = null,
    ): OperationScope {
        return $this->logger->startOperation($traceId, $parentSpanId, $methodName, $url);
    }

    /**
     * Execute $work inside a trace-scope bound to $traceId (or a new id when
     * $traceId is null). The previous trace-id is restored on exit, even on
     * exception. Use this at every public entry point (HTTP controller, CLI
     * command, MQ handler) - see observability-contract.md.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function withOperationScope(?string $traceId, callable $work): mixed
    {
        $previous = $this->scope->get();
        $this->scope->set($traceId);
        try {
            return $work();
        } finally {
            $this->scope->set($previous);
        }
    }
}
