<?php

declare(strict_types=1);

namespace Unit;

use GraftCodeLogger\Logger\Logger;
use GraftCodeLogger\LoggerClient;
use GraftCodeLogger\Telemetry\TelemetryChannel;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TelemetryChannelTest extends TestCase
{
    protected function setUp(): void
    {
        TelemetryChannel::reset();
        putenv('LOGGER_QUEUE_MAX');
    }

    protected function tearDown(): void
    {
        TelemetryChannel::reset();
        putenv('LOGGER_QUEUE_MAX');
    }

    #[Group('unit')]
    public function testFlushBatchesByOneHundred(): void
    {
        $history = [];
        $this->bindClient([new Response(200), new Response(200), new Response(200)], $history);

        for ($i = 0; $i < 250; $i++) {
            TelemetryChannel::instance()->enqueue([
                'name' => 'Microsoft.ApplicationInsights.Message',
                'iKey' => 'ikey',
                'data' => ['n' => $i],
            ]);
        }

        TelemetryChannel::instance()->flush();

        $this->assertCount(3, $history);
        $this->assertCount(100, json_decode($history[0], true));
        $this->assertCount(100, json_decode($history[1], true));
        $this->assertCount(50, json_decode($history[2], true));
        $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
    }

    #[Group('unit')]
    public function testOverflowDropsOldestAndReportsCount(): void
    {
        putenv('LOGGER_QUEUE_MAX=2');
        $history = [];
        $this->bindClient([new Response(200)], $history);

        for ($i = 1; $i <= 5; $i++) {
            TelemetryChannel::instance()->enqueue([
                'name' => 'Microsoft.ApplicationInsights.Message',
                'iKey' => 'ikey',
                'data' => ['n' => $i],
            ]);
        }

        $this->assertSame(3, TelemetryChannel::instance()->droppedCount());
        TelemetryChannel::instance()->flush();

        $this->assertCount(1, $history);
        $batch = json_decode($history[0], true);
        $this->assertCount(3, $batch);
        $this->assertSame(4, $batch[0]['data']['n']);
        $this->assertSame(5, $batch[1]['data']['n']);
        $this->assertSame('telemetry_dropped_count=3', $batch[2]['data']['baseData']['message']);
    }

    #[Group('unit')]
    public function testQueueMaxIsReadAgainAfterReset(): void
    {
        TelemetryChannel::instance()->enqueue([
            'name' => 'Microsoft.ApplicationInsights.Message',
            'iKey' => 'ikey',
            'data' => ['n' => 1],
        ]);
        TelemetryChannel::reset();
        putenv('LOGGER_QUEUE_MAX=1');

        TelemetryChannel::instance()->enqueue([
            'name' => 'Microsoft.ApplicationInsights.Message',
            'iKey' => 'ikey',
            'data' => ['n' => 1],
        ]);
        TelemetryChannel::instance()->enqueue([
            'name' => 'Microsoft.ApplicationInsights.Message',
            'iKey' => 'ikey',
            'data' => ['n' => 2],
        ]);

        $this->assertSame(1, TelemetryChannel::instance()->droppedCount());
        $this->assertSame(1, TelemetryChannel::instance()->pendingCount());
    }

    #[Group('unit')]
    public function testEnqueueMarksShutdownFlush(): void
    {
        TelemetryChannel::instance()->enqueue([
            'name' => 'Microsoft.ApplicationInsights.Message',
            'iKey' => 'ikey',
        ]);

        $flag = (new ReflectionClass(TelemetryChannel::instance()))->getProperty('shutdownRegistered');
        $this->assertTrue($flag->getValue(TelemetryChannel::instance()));
    }

    #[Group('unit')]
    public function testObservedAnalyzeVolumeDoesNotBlockCaller(): void
    {
        $lineCount = 434;
        $simulatedRttMicros = 120_000;
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');

        try {
            $calls = 0;
            $mock = new MockHandler(array_fill(0, 10, new Response(200)));
            $stack = HandlerStack::create($mock);
            $stack->push(static function (callable $handler) use (&$calls, $simulatedRttMicros) {
                return static function ($request, array $options) use ($handler, &$calls, $simulatedRttMicros) {
                    $calls++;
                    usleep($simulatedRttMicros);

                    return $handler($request, $options);
                };
            });
            TelemetryChannel::instance()->setHttpClient(new GuzzleClient([
                'handler' => $stack,
                'http_errors' => false,
            ]));

            $logger = new Logger(new LoggerClientOptions('gma-php-logger-stress-test', 'GraftCodeAnalyzerPhp'));
            $callerStarted = microtime(true);
            for ($i = 0; $i < $lineCount; $i++) {
                $logger->trackVerbose('analyze step ' . $i, ['i' => $i]);
            }
            $callerMs = (microtime(true) - $callerStarted) * 1000;

            $this->assertSame(0, $calls);
            $this->assertSame($lineCount, TelemetryChannel::instance()->pendingCount());
            $this->assertLessThan(1000, $callerMs);

            $flushStarted = microtime(true);
            TelemetryChannel::instance()->flush();
            $flushMs = (microtime(true) - $flushStarted) * 1000;
            $syncEquivalentMs = $lineCount * ($simulatedRttMicros / 1000);

            $this->assertSame(5, $calls);
            $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
            $this->assertSame(0, TelemetryChannel::instance()->flushFailureCount());
            $this->assertLessThan($syncEquivalentMs / 10, $flushMs);

            fwrite(STDERR, sprintf(
                "verbose export metrics: lines=%d caller_ms=%.1f flush_ms=%.1f http_calls=%d sync_equivalent_ms=%.0f\n",
                $lineCount,
                $callerMs,
                $flushMs,
                $calls,
                $syncEquivalentMs
            ));
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackVerboseLoopDoesNotWaitForHttp(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');

        try {
            $calls = 0;
            $mock = new MockHandler(array_fill(0, 10, new Response(200)));
            $stack = HandlerStack::create($mock);
            $stack->push(static function (callable $handler) use (&$calls) {
                return static function ($request, array $options) use ($handler, &$calls) {
                    $calls++;
                    usleep(100_000);

                    return $handler($request, $options);
                };
            });
            TelemetryChannel::instance()->setHttpClient(new GuzzleClient([
                'handler' => $stack,
                'http_errors' => false,
            ]));

            $logger = new Logger(new LoggerClientOptions('testLogger', 'php'));
            $started = microtime(true);
            for ($i = 0; $i < 500; $i++) {
                $logger->trackVerbose('line ' . $i, ['i' => $i]);
            }
            $elapsed = microtime(true) - $started;

            $this->assertLessThan(1.0, $elapsed);
            $this->assertSame(0, $calls);
            $this->assertSame(500, TelemetryChannel::instance()->pendingCount());

            TelemetryChannel::instance()->flush();
            $this->assertSame(5, $calls);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testLoggerClientFlushSendsQueuedVerbose(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');

        try {
            $history = [];
            $this->bindClient([new Response(200)], $history);

            $client = new LoggerClient(new LoggerClientOptions('testLogger', 'php'));
            $client->getLogger()->trackVerbose('queued line', ['i' => 1]);

            $this->assertSame([], $history);
            $this->assertSame(1, TelemetryChannel::instance()->pendingCount());

            $client->flush();

            $this->assertCount(1, $history);
            $batch = json_decode($history[0], true);
            $this->assertCount(1, $batch);
            $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    /**
     * @param list<Response> $responses
     * @param list<string> $history
     */
    private function bindClient(array $responses, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(static function (callable $handler) use (&$history) {
            return static function ($request, array $options) use ($handler, &$history) {
                $history[] = (string) $request->getBody();

                return $handler($request, $options);
            };
        });
        TelemetryChannel::instance()->setHttpClient(new GuzzleClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));
    }
}
