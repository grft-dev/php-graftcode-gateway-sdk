<?php

declare(strict_types=1);

namespace GraftCodeLogger\Telemetry;

use GraftCodeLogger\Utils\Enum\SeverityLevel;
use GraftCodeLogger\Utils\Time;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Process-wide buffer for Application Insights envelopes.
 * track* only enqueues. HTTP happens in flush(), once per batch.
 */
final class TelemetryChannel
{
    public const int DEFAULT_MAX_QUEUE = 1000;
    public const int BATCH_SIZE = 100;
    public const float CONNECT_TIMEOUT_SECONDS = 1.0;
    public const float TIMEOUT_SECONDS = 2.0;
    public const float FLUSH_BUDGET_SECONDS = 3.0;
    public const string TRACK_ENDPOINT = 'https://dc.services.visualstudio.com/v2/track';

    private static ?self $instance = null;

    /** @var list<array<string, mixed>> */
    private array $queue = [];
    private int $dropped = 0;
    private int $flushFailures = 0;
    private bool $shutdownRegistered = false;
    private ?string $lastInstrumentationKey = null;
    private ?GuzzleClient $httpClient = null;
    private ?int $maxQueue = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        if (self::$instance === null) {
            return;
        }

        self::$instance->queue = [];
        self::$instance->dropped = 0;
        self::$instance->flushFailures = 0;
        self::$instance->lastInstrumentationKey = null;
        self::$instance->httpClient = null;
        self::$instance->maxQueue = null;
    }

    public function setHttpClient(GuzzleClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    public function pendingCount(): int
    {
        return count($this->queue);
    }

    public function droppedCount(): int
    {
        return $this->dropped;
    }

    public function flushFailureCount(): int
    {
        return $this->flushFailures;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    public function enqueue(array $envelope): void
    {
        $this->registerShutdown();

        $max = $this->maxQueueSize();
        while (count($this->queue) >= $max) {
            array_shift($this->queue);
            $this->dropped++;
        }

        $this->queue[] = $envelope;
        if (isset($envelope['iKey']) && is_string($envelope['iKey']) && $envelope['iKey'] !== '') {
            $this->lastInstrumentationKey = $envelope['iKey'];
        }
    }

    public function flush(): void
    {
        if ($this->dropped > 0) {
            $dropped = $this->dropped;
            $this->dropped = 0;
            error_log(sprintf('WARNING: GCLogger: telemetry_dropped_count=%d', $dropped));
            if ($this->lastInstrumentationKey !== null) {
                $this->queue[] = $this->droppedEnvelope($dropped);
            }
        }

        if ($this->queue === []) {
            return;
        }

        $deadline = microtime(true) + self::FLUSH_BUDGET_SECONDS;

        while ($this->queue !== []) {
            if (microtime(true) >= $deadline) {
                $left = count($this->queue);
                $this->queue = [];
                $this->flushFailures++;
                error_log(sprintf('ERROR: GCLogger: flush budget exceeded, dropped %d pending telemetry items', $left));
                return;
            }

            $batch = array_splice($this->queue, 0, self::BATCH_SIZE);
            $this->postBatch($batch, $deadline);
        }
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            register_shutdown_function(static function (): void {
                self::$instance?->flush();
            });
        });
    }

    private function maxQueueSize(): int
    {
        if ($this->maxQueue !== null) {
            return $this->maxQueue;
        }

        $raw = getenv('LOGGER_QUEUE_MAX');
        if (!is_string($raw) || trim($raw) === '') {
            return $this->maxQueue = self::DEFAULT_MAX_QUEUE;
        }

        $parsed = filter_var(trim($raw), FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            return $this->maxQueue = self::DEFAULT_MAX_QUEUE;
        }

        return $this->maxQueue = $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function droppedEnvelope(int $dropped): array
    {
        return [
            'name' => 'Microsoft.ApplicationInsights.Message',
            'time' => Time::getFormattedString(),
            'iKey' => $this->lastInstrumentationKey,
            'tags' => ['ai.operation.id' => 'telemetry-channel'],
            'data' => [
                'baseType' => 'MessageData',
                'baseData' => [
                    'message' => sprintf('telemetry_dropped_count=%d', $dropped),
                    'severityLevel' => SeverityLevel::WARNING->value,
                    'properties' => (object) ['telemetry_dropped_count' => (string) $dropped],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $batch
     */
    private function postBatch(array $batch, float $deadline): void
    {
        $json = json_encode(array_values($batch));
        if ($json === false) {
            foreach ($batch as $item) {
                $one = json_encode([$item]);
                if ($one === false) {
                    $this->flushFailures++;
                    error_log('ERROR: GCLogger: Error encoding JSON for Application Insights: ' . json_last_error_msg());
                    continue;
                }
                $this->postJson($one, $deadline, true);
            }
            return;
        }

        $this->postJson($json, $deadline, true);
    }

    private function postJson(string $json, float $deadline, bool $allowRetry): void
    {
        if (microtime(true) >= $deadline) {
            $this->flushFailures++;
            error_log('ERROR: GCLogger: flush budget exceeded before telemetry POST');
            return;
        }

        try {
            $response = $this->httpClient()->post(self::TRACK_ENDPOINT, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $json,
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::TIMEOUT_SECONDS,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            $this->flushFailures++;
            error_log('ERROR: GCLogger: Error sending log to Application Insights: ' . $e->getMessage());
            return;
        } catch (Throwable $e) {
            $this->flushFailures++;
            error_log('ERROR: GCLogger: Unexpected error during log send: ' . $e->getMessage());
            return;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200 || $statusCode === 202) {
            return;
        }

        if ($allowRetry && ($statusCode === 429 || $statusCode === 503) && microtime(true) < $deadline) {
            $this->postJson($json, $deadline, false);
            return;
        }

        $this->flushFailures++;
        error_log(sprintf(
            'ERROR: GCLogger: Application Insights reported non-200/202 | Status: %d | Body: %s',
            $statusCode,
            $response->getBody()->getContents()
        ));
    }

    private function httpClient(): GuzzleClient
    {
        return $this->httpClient ??= new GuzzleClient();
    }
}
