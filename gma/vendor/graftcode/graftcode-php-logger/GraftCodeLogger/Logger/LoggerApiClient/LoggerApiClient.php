<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger\LoggerApiClient;

use DateMalformedStringException;
use GraftCodeLogger\Utils\{
    Enum\SeverityLevel,
    Time,
    VO\LoggerPayloadTags
};
use GraftCodeLogger\Telemetry\TelemetryChannel;
use Throwable;

final readonly class LoggerApiClient implements LoggerApiClientInterface
{
    public function __construct(private string $instrumentationKey)
    {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function trackTrace(string $message, SeverityLevel $severity, LoggerPayloadTags $payloadTags, array $properties = []): void
    {
        $this->sendLog(
            'Microsoft.ApplicationInsights.Message',
            $payloadTags,
            [
                'message' => $message,
                'severityLevel' => $severity->value,
                'properties' => (object) $properties
            ]
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    public function trackMetric(string $name, float|int|string $value, LoggerPayloadTags $payloadTags, array $properties = []): void
    {
        $this->sendLog(
            'Microsoft.ApplicationInsights.Metric',
            $payloadTags,
            [
                'metrics' => [['name' => $name, 'value' => $value]],
                'properties' => (object) $properties
            ]
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    public function trackRequest(
        string $name,
        string $url,
        string|float|int $durationMs,
        bool $success,
        int|string $responseCode,
        LoggerPayloadTags $payloadTags,
        array $properties = [],
        ?string $timeOverride = null
    ): void {
        $requestId = $payloadTags->getSpanId() ?? $payloadTags->getOperationId();

        $this->sendLog(
            'Microsoft.ApplicationInsights.Request',
            $payloadTags,
            [
                'id' => $requestId,
                'name' => $name,
                'url' => $url,
                'duration' => self::formatDurationMs($durationMs),
                'responseCode' => (string) $responseCode,
                'success' => $success,
                'properties' => (object) $properties
            ],
            $timeOverride,
        );
    }

    
    //(D.HH:MM:SS.ffffff)
    private static function formatDurationMs(float|int|string $durationMs): string
    {
        $ms = (float) $durationMs;
        if ($ms < 0 || is_nan($ms) || is_infinite($ms)) {
            $ms = 0.0;
        }

        $totalMicroseconds = (int) round($ms * 1000);
        $microseconds = $totalMicroseconds % 1_000_000;
        $totalSeconds = intdiv($totalMicroseconds, 1_000_000);

        $seconds = $totalSeconds % 60;
        $totalMinutes = intdiv($totalSeconds, 60);

        $minutes = $totalMinutes % 60;
        $totalHours = intdiv($totalMinutes, 60);

        $hours = $totalHours % 24;
        $days = intdiv($totalHours, 24);

        return sprintf('%d.%02d:%02d:%02d.%06d', $days, $hours, $minutes, $seconds, $microseconds);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function trackEvent(string $name, LoggerPayloadTags $payloadTags, array $properties = []): void
    {
        $this->sendLog(
            'Microsoft.ApplicationInsights.Event',
            $payloadTags,
            [
                'name' => $name,
                'properties' => (object) $properties
            ]
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    public function trackException(Throwable $exception, LoggerPayloadTags $payloadTags, array $properties = []): void
    {
       $this->sendLog(
            'Microsoft.ApplicationInsights.Exception',
            $payloadTags,
            [
                'exceptions' => [
                    [
                        'typeName' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'hasFullStack' => true,
                        'parsedStack' => $this->parseStackTrace($exception),
                    ]
                ],
                'properties' => (object) $properties
            ]
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function sendLog(
        string $type,
        LoggerPayloadTags $payloadTags,
        array $baseData,
        ?string $timeOverride = null,
    ): void {
        //ai.operation.id - App Insights Operation Tag
        $tags = ['ai.operation.id' => $payloadTags->getOperationId()];
        $parentId = $payloadTags->getParentId();
        if ($parentId !== null) {
            $tags['ai.operation.parentId'] = $parentId;
        }

        $payload = [
            'name' => $type,
            'time' => $timeOverride ?? Time::getFormattedString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $tags,
            'data' => [
                'baseType' => $this->getBaseTypeName($type),
                'baseData' => $baseData
            ]
        ];

        $this->enqueue($payload);
    }

    private function getBaseTypeName(string $type): string
    {
        $parts = explode('.', $type);

        return end($parts) . 'Data';
    }

    private function parseStackTrace(Throwable $exception): array
    {
        $parsedStack = [];
        $frames = $exception->getTrace();

        $parsedStack[] = [
            'level' => 0,
            'method' => '{main}',
            'assembly' => 'PHP Application',
            'fileName' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        foreach ($frames as $idx => $frame) {
            $methodName = '';
            if (isset($frame['class'])) {
                $methodName .= $frame['class'];
            }
            if (isset($frame['type'])) {
                $methodName .= $frame['type'];
            }
            if (isset($frame['function'])) {
                $methodName .= $frame['function'];
            }
            if (empty($methodName)) {
                $methodName = $frame['function'] ?? '[internal function]';
            }

            $parsedStack[] = [
                'level' => $idx + 1,
                'method' => $methodName,
                'assembly' => 'PHP Application',
                'fileName' => $frame['file'] ?? 'N/A',
                'line' => $frame['line'] ?? 0,
            ];
        }

        return $parsedStack;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function enqueue(array $envelope): void
    {
        TelemetryChannel::instance()->enqueue($envelope);
    }
}
