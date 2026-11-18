<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger;

use GraftCodeLogger\Logger\Scope\OperationScope;
use Throwable;

interface LoggerInterface
{
    public function trackEvent(string $name, array $properties = []): null;
    public function trackException(Throwable $exception, array $properties = []): null;
    public function trackMetric(string $name, float|int|string $value, array $properties = []): null;
    public function trackRequest(string $name, string $url, float|int|string $duration, bool $success, string $responseCode, array $properties = []): null;
    public function trackInfo(string $message, array $properties = []): null;
    public function trackVerbose(string $message, array $properties = []): null;
    public function trackWarning(string $message, array $properties = []): null;
    public function trackError(string $message, array $properties = []): null;
    public function trackCritical(string $message, array $properties = []): null;
    public function startOperation(?string $traceId = null, ?string $parentSpanId = null, ?string $methodName = null, ?string $url = null): OperationScope;
    public function getTraceId(): string;
}
