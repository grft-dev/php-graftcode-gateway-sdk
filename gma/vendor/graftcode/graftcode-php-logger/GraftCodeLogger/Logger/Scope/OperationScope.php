<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger\Scope;

use Exception;
use GraftCodeLogger\Logger\LoggerApiClient\LoggerApiClientInterface;
use GraftCodeLogger\Utils\Enum\PropertiesName;
use GraftCodeLogger\Utils\SpanId;
use GraftCodeLogger\Utils\Time;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GraftCodeLogger\Utils\VO\LoggerPayloadTags;
use GraftCodeLogger\Utils\VO\TraceScope;
use Throwable;


final class OperationScope
{
    private readonly float $startedAt;
    private readonly string $spanId;
    private readonly string $traceId;
    private readonly string $previousTraceId;
    private readonly ?string $previousParentSpanId;
    private readonly ?string $incomingParentSpanId;
    private readonly string $operationName;
    private bool $closed = false;

    public function __construct(
        private readonly TraceScope $scope,
        private readonly ?LoggerApiClientInterface $apiClient,
        private readonly LoggerClientOptions $config,
        ?string $traceId = null,
        ?string $parentSpanId = null,
        ?string $methodName = null,
        private readonly ?string $url = null,
    ) {
        $this->previousTraceId = $scope->get();
        $this->previousParentSpanId = $scope->getParentSpanId();

        $this->traceId = self::normalizeTraceId($traceId) ?? self::generateW3CTraceId();
        $this->spanId = SpanId::generate()->toString();

        $this->incomingParentSpanId = self::normalizeSpanId($parentSpanId)
            ?? $this->previousParentSpanId;

        $this->operationName = $methodName !== null && trim($methodName) !== ''
            ? $methodName
            : 'operation';
        $this->startedAt = microtime(true);

        $scope->set($this->traceId);
        $scope->setParentSpanId($this->spanId);
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function getDurationMs(): float
    {
        return (microtime(true) - $this->startedAt) * 1000.0;
    }

    public function close(bool $success = true, string $responseCode = '200'): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        $durationMs = (microtime(true) - $this->startedAt) * 1000.0;

        try {
            $this->emitRootRequest($durationMs, $success, $responseCode);
        } catch (Throwable) {
            // Telemetry failures must never propagate into business code.
        } finally {
            $this->scope->set($this->previousTraceId);
            $this->scope->setParentSpanId($this->previousParentSpanId);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function emitRootRequest(float $durationMs, bool $success, string $responseCode): void
    {
        $properties = [
            PropertiesName::APP_SERVICE_NAME->value => $this->config->appServiceName,
            PropertiesName::SERVICE_NAME->value => $this->config->serviceName,
            PropertiesName::METHOD_NAME->value => $this->operationName,
            PropertiesName::TRACE_ID->value => $this->traceId,
            PropertiesName::OPERATION_ID->value => $this->traceId,
            'SpanId' => $this->spanId,
        ];

        if ($this->apiClient !== null) {
            $tags = new LoggerPayloadTags(
                $this->traceId,
                spanId: $this->spanId,
                parentId: $this->incomingParentSpanId,
            );

            $this->apiClient->trackRequest(
                $this->operationName,
                $this->url ?? $this->operationName,
                $durationMs,
                $success,
                $responseCode,
                $tags,
                $properties,
                Time::getFormattedStringFromEpoch($this->startedAt),
            );
            return;
        }

        echo sprintf(
            "INFORMATION: REQUEST: %s [%s], Duration: %s ms, Success: %s, ResponseCode: %s | %s\n",
            $this->operationName,
            $this->url ?? $this->operationName,
            $durationMs,
            $success,
            $responseCode,
            json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    private static function normalizeTraceId(?string $traceId): ?string
    {
        if ($traceId === null || trim($traceId) === '') {
            return null;
        }

        $candidate = strtolower(str_replace('-', '', $traceId));
        if (strlen($candidate) !== 32) {
            return null;
        }
        if (preg_match('/^[0-9a-f]{32}$/', $candidate) !== 1) {
            return null;
        }
        return $candidate;
    }

    private static function normalizeSpanId(?string $spanId): ?string
    {
        if ($spanId === null || trim($spanId) === '') {
            return null;
        }

        $candidate = strtolower(trim($spanId));
        if (strlen($candidate) !== 16 || $candidate === '0000000000000000') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{16}$/', $candidate) !== 1) {
            return null;
        }
        return $candidate;
    }

    private static function generateW3CTraceId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception) {
            $hex = '';
            for ($i = 0; $i < 16; $i++) {
                $hex .= sprintf('%02x', mt_rand(0, 255));
            }
            return $hex;
        }
    }
}
