<?php

declare(strict_types=1);

namespace E2E;

use Exception;
use GraftCodeLogger\Logger\Logger;
use GraftCodeLogger\LoggerClient;
use GraftCodeLogger\Telemetry\TelemetryChannel;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Throwable;

final class LoggerTest extends TestCase
{
    private const string UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private Logger $logger;
    private LoggerClient $loggerClient;

    protected function setUp(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        if (!is_string($existingKey) || trim($existingKey) === '') {
            $this->fail('Not configured APPLICATIONINSIGHTS_CONNECTION_STRING to run E2E tests');
        }

        $parts = [];
        parse_str(str_replace(';', '&', $existingKey), $parts);
        $instrumentationKey = $parts['InstrumentationKey'] ?? '';
        if (!is_string($instrumentationKey) || trim($instrumentationKey) === '') {
            $this->fail('APPLICATIONINSIGHTS_CONNECTION_STRING environment variable is required for this test');
        }

        if (preg_match(self::UUID_V4_PATTERN, $instrumentationKey) !== 1) {
            $this->fail('APPLICATIONINSIGHTS_CONNECTION_STRING environment variable must have format UUID version 4');
        }

        TelemetryChannel::reset();

        $this->loggerClient = new LoggerClient(new LoggerClientOptions(
            'gc-d-app-polc-gcg-php-01-test',
            'GraftCodeGeneratorTest'
        ));
        $this->logger = $this->loggerClient->getLogger();
    }

    protected function tearDown(): void
    {
        TelemetryChannel::reset();
    }

    private function assertAcceptedAndFlushed(): void
    {
        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());

        $started = microtime(true);
        $this->loggerClient->flush();
        $flushMs = (microtime(true) - $started) * 1000;

        $this->assertSame(0, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
        $this->assertLessThan(5000, $flushMs);
        fwrite(STDERR, sprintf("e2e flush_ms=%.1f\n", $flushMs));
    }

    /**
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackEventToExternalEndpoint(): void
    {
        $name = 'Test E2E track event message';
        $properties = ['environment' => 'test_e2e_track_event', 'technology' => 'php'];

        $this->logger->trackEvent($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackMetricToExternalEndpoint(): void
    {
        $name = 'Test E2E track metric message';
        $properties = ['environment' => 'test_e2e_track_metric', 'technology' => 'php'];

        $this->logger->trackMetric($name, 1000, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackInfoToExternalEndpoint(): void
    {
        $name = 'Test E2E track info message';
        $properties = ['environment' => 'test_e2e_track_info', 'technology' => 'php'];

        $this->logger->trackInfo($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackVerboseToExternalEndpoint(): void
    {
        $name = 'Test E2E track verbose message';
        $properties = ['environment' => 'test_e2e_track_verbose', 'technology' => 'php'];

        $this->logger->trackVerbose($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws Throwable
     * @throws GuzzleException
     */
    #[Group('e2e')]
    public function testSendTrackWarningToExternalEndpoint(): void
    {
        $name = 'Test E2E track warning message';
        $properties = ['environment' => 'test_e2e_track_warning', 'technology' => 'php'];

        $this->logger->trackWarning($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackErrorToExternalEndpoint(): void
    {
        $name = 'Test E2E track error message';
        $properties = ['environment' => 'test_e2e_track_error', 'technology' => 'php'];

        $this->logger->trackError($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackExceptionToExternalEndpoint(): void
    {
        $exception = new Exception('Test E2E track Exception message');
        $properties = ['environment' => 'test_e2e_track_Exception', 'technology' => 'php'];

        $this->logger->trackException($exception, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    #[Group('e2e')]
    public function testSendTrackCriticalToExternalEndpoint(): void
    {
        $name = 'Test E2E track critical message';
        $properties = ['environment' => 'test_e2e_track_critical', 'technology' => 'php'];

        $this->logger->trackCritical($name, $properties);

        $this->assertAcceptedAndFlushed();
    }

    /**
     * @throws Throwable
     * @throws GuzzleException
     */
    #[Group('e2e')]
    public function testSendTrackRequestToExternalEndpoint(): void
    {
        $name = 'Test E2E track Request message';
        $url = 'https://example.com/api/test';
        $duration = 150;
        $success = true;
        $responseCode = '200';
        $properties = ['environment' => 'test_e2e_track_Request', 'technology' => 'php'];

        $this->logger->trackRequest($name, $url, $duration, $success, $responseCode, $properties);

        $this->assertAcceptedAndFlushed();
    }
}
