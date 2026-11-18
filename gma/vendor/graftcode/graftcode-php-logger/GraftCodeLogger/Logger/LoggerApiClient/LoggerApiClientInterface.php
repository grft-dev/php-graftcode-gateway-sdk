<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger\LoggerApiClient;

use GraftCodeLogger\Utils\{
    Enum\SeverityLevel,
    VO\LoggerPayloadTags
};
use Throwable;

interface LoggerApiClientInterface
{
    public function trackTrace(string $message, SeverityLevel $severity, LoggerPayloadTags $payloadTags, array $properties = []): void;
    public function trackMetric(string $name, float|int|string $value, LoggerPayloadTags $payloadTags, array $properties = []): void;
    public function trackRequest(string $name, string $url, string|float|int $durationMs, bool $success, int|string $responseCode, LoggerPayloadTags $payloadTags, array $properties = [], ?string $timeOverride = null): void;
    public function trackEvent(string $name, LoggerPayloadTags $payloadTags, array $properties = []): void;
    public function trackException(Throwable $exception, LoggerPayloadTags $payloadTags, array $properties = []): void;
}
