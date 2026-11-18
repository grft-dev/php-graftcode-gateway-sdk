<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger;

use GraftCodeLogger\Logger\LoggerApiClient\{
    LoggerApiClient,
    LoggerApiClientInterface
};
use GraftCodeLogger\Logger\Scope\OperationScope;
use GraftCodeLogger\Utils\{
    Config\EnvironmentConfig,
    Enum\PropertiesName,
    Enum\SeverityLevel,
    Time,
    VO\LoggerClientOptions,
    VO\LoggerPayloadTags,
    VO\TraceScope
};
use Throwable;

final class Logger implements LoggerInterface
{
    private ?LoggerApiClientInterface $client = null;
    private readonly TraceScope $scope;

    public function __construct(
        private readonly LoggerClientOptions $config,
        ?TraceScope $scope = null,
    ) {
        $this->scope = $scope ?? new TraceScope();
        $actualKey = EnvironmentConfig::getInstrumentationKey();
        if ($actualKey) {
            $this->client = new LoggerApiClient($actualKey);
        }
    }

    public function getTraceScope(): TraceScope
    {
        return $this->scope;
    }

    public function getTraceId(): string
    {
        return $this->scope->get();
    }

    public function startOperation(
        ?string $traceId = null,
        ?string $parentSpanId = null,
        ?string $methodName = null,
        ?string $url = null,
    ): OperationScope {
        return new OperationScope(
            $this->scope,
            $this->client,
            $this->config,
            $traceId,
            $parentSpanId,
            $methodName,
            $url,
        );
    }

    private function currentTags(): LoggerPayloadTags
    {
        return new LoggerPayloadTags(
            $this->scope->get(),
            spanId: null,
            parentId: $this->scope->getParentSpanId(),
        );
    }

    private function formatProperties(array $properties): string
    {
        return json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws Throwable
     */
    public function trackEvent(string $name, array $properties = []): null
    {
        if ($this->client) {
            $this->client->trackEvent(
                $name,
                $this->currentTags(),
                $this->getFormatProperties($properties)
            );

            return null;
        }

        echo sprintf("INFORMATION: EVENT: %s | %s\n",
            $name, $this->formatProperties($this->getFormatProperties($properties))
        );

        return null;
    }

    /**
     * @throws Throwable
     */
    public function trackException(Throwable $exception, array $properties = []): null
    {
        if ($this->client) {
            $this->client->trackException(
                $exception,
                $this->currentTags(),
                $this->getFormatProperties($properties)
            );

            return null;
        }

        echo sprintf(
            "ERROR: EXCEPTION: %s - %s\n%s Properties: %s\n",
            get_class($exception), $exception->getMessage(),
            $exception->getTraceAsString(), $this->formatProperties($properties)
        );

        return null;
    }

    /**
     * @throws Throwable
     */
    public function trace(string $message, SeverityLevel $severity, array $properties = []): null
    {
        $message = $this->getFormatMessage($message, $severity);
        $formattedProperties = $this->getFormatProperties($properties, $severity !== SeverityLevel::VERBOSE);
        if ($this->client) {
            $this->client->trackTrace(
                $message,
                $severity,
                $this->currentTags(),
                $formattedProperties
            );

            return null;
        }

        echo sprintf(
            "%s: TRACE: %s | %s\n",
            $severity->name, $message, $this->formatProperties($formattedProperties)
        );

        return null;
    }

    /**
     * @throws Throwable
     */
    public function trackMetric(string $name, float|int|string $value, array $properties = []): null
    {
        if ($this->client) {
            $this->client->trackMetric(
                $name,
                $value,
                $this->currentTags(),
                $this->getFormatProperties($properties));

            return null;
        }

        echo sprintf("INFORMATION: METRIC: %s=%s | %s\n",
            $name, $value, $this->formatProperties($this->getFormatProperties($properties))

        );

        return null;
    }

    /**
     * @throws Throwable
     */
    public function trackRequest(
        string $name,
        string $url,
        float|int|string $duration,
        bool $success,
        string $responseCode,
        array $properties = []
    ): null {
        if ($this->client) {
            $this->client->trackRequest(
                $name,
                $url,
                $duration,
                $success,
                $responseCode,
                $this->currentTags(),
                $this->getFormatProperties($properties));

            return null;
        }

        echo sprintf("INFORMATION: REQUEST: %s [%s], Duration: %s ms, Success: %s, ResponseCode: %s | %s\n",
            $name, $url, $duration, $success, $responseCode, $this->formatProperties($this->getFormatProperties($properties))
        );

        return null;
    }

    /**
     * @throws Throwable
     */
    public function trackInfo(string $message, array $properties = []): null
    {
        return $this->trace($message, SeverityLevel::INFORMATION, $properties);
    }

    /**
     * @throws Throwable
     */
    public function trackVerbose(string $message, array $properties = []): null
    {
        return $this->trace($message, SeverityLevel::VERBOSE, $properties);
    }

    /**
     * @throws Throwable
     */
    public function trackWarning(string $message, array $properties = []): null
    {
        return $this->trace($message, SeverityLevel::WARNING, $properties);
    }

    /**
     * @throws Throwable
     */
    public function trackError(string $message, array $properties = []): null
    {
        return $this->trace($message, SeverityLevel::ERROR, $properties);
    }

    /**
     * @throws Throwable
     */
    public function trackCritical(string $message, array $properties = []): null
    {
        return $this->trace($message, SeverityLevel::CRITICAL, $properties);
    }

    private function getFormatMessage(string $message, SeverityLevel $severityLevel): string
    {
        $parts = [
            Time::getFormattedString(),
            $severityLevel->name,
        ];
        if ($this->config->appServiceName !== '') {
            $parts[] = $this->config->appServiceName;
        }
        if ($this->config->serviceName !== '') {
            $parts[] = $this->config->serviceName;
        }
        if ($message !== '') {
            $parts[] = 'Message: ' . $message;
        }

        return implode(' - ', $parts);
    }

    private function getCallerContext(array $properties = []): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        $callLocation = $this->findLoggerCallLocation($backtrace);
        $appContext = $this->findApplicationContext($backtrace);

        if ($callLocation && $appContext) {
            $properties[PropertiesName::CLASS_NAME->value] = $appContext['class'];
            $properties[PropertiesName::METHOD_NAME->value] = $appContext['function'];
            $properties[PropertiesName::FILE->value] = basename($callLocation['file']);
            $properties[PropertiesName::LINE->value] = $callLocation['line'];
        }

        return $properties;
    }

    private function findLoggerCallLocation(array $backtrace): ?array
    {
        foreach ($backtrace as $trace) {
            if ($this->isLoggerCallFromApplication($trace)) {
                return $trace;
            }
        }

        return null;
    }

    private function isLoggerCallFromApplication(array $trace): bool
    {
        return isset($trace['function'], $trace['file'], $trace['line'], $trace['class'])
            && $trace['class'] === self::class
            && str_starts_with($trace['function'], 'track')
            && !str_contains($trace['file'], 'Logger.php');
    }

    private function findApplicationContext(array $backtrace): ?array
    {
        foreach ($backtrace as $trace) {
            if ($this->isApplicationClass($trace)) {
                return $trace;
            }
        }

        return null;
    }

    private function isApplicationClass(array $trace): bool
    {
        if (!isset($trace['class']) || $trace['class'] === self::class) {
            return false;
        }

        return !str_contains($trace['class'], 'Logger')
            && !str_contains($trace['class'], 'PHPUnit')
            && !str_ends_with($trace['class'], 'Test');
    }

    private function getFormatProperties(array $properties, bool $includeCaller = true): array
    {
        $traceId = $this->scope->get();
        $formattedProperties = [
            PropertiesName::APP_SERVICE_NAME->value => $this->config->appServiceName,
            PropertiesName::SERVICE_NAME->value => $this->config->serviceName,
            PropertiesName::TRACE_ID->value => $traceId,
            // Legacy alias retained for one release - dashboards MUST migrate
            // to TraceId (see graftcode-protocol/docs/observability-contract.md).
            PropertiesName::OPERATION_ID->value => $traceId,
        ];

        if (!$includeCaller) {
            return array_merge($formattedProperties, $properties);
        }

        return array_merge($formattedProperties, $this->getCallerContext($properties));
    }
}
