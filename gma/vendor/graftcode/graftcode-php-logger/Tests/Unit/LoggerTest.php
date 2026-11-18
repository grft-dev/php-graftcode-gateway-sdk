<?php

declare(strict_types=1);

namespace Unit;

use Exception;
use GraftCodeLogger\Logger\Logger;
use GraftCodeLogger\Logger\LoggerApiClient\LoggerApiClient;
use GraftCodeLogger\Logger\LoggerApiClient\LoggerApiClientInterface;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\Telemetry\TelemetryChannel;
use GraftCodeLogger\Utils\Enum\SeverityLevel;
use GraftCodeLogger\Utils\Uuid;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GraftCodeLogger\Utils\VO\LoggerPayloadTags;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class LoggerTest extends TestCase
{
    private MockObject|LoggerApiClient $loggerMock;
    private LoggerClientOptions $logger;

    protected function setUp(): void
    {
        $this->logger = new LoggerClientOptions('testLogger', 'php');
    }

    private function setUpLoggerMock(): void
    {
        $this->loggerMock = $this->createMock(LoggerApiClientInterface::class);
    }

    #[Group('unit')]
    public function testConstructorInitializesLoggerWhenInstrumentationKeyIsProvidedAndNotNullLoggerApiClient(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd;IngestionEndpoint=');

        try {
            $logger = new Logger($this->logger);

            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorDoesNotInitializeLoggerWhenEnvIsNotSetOrEmpty(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $return = $logger->trackEvent('Test unit PHP Event');
            $output = ob_get_clean();
            
            // Check that the output contains the expected event format
            $this->assertStringContainsString("INFORMATION: EVENT: Test unit PHP Event", $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
            $this->assertNull($return);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorDoesNotInitializeLoggerWhenIsLocalTrue(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);

            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $return = $logger->trackEvent('Test unit PHP Event Local');
            $output = ob_get_clean();
            
            // Check that the output contains the expected event format
            $this->assertStringContainsString("INFORMATION: EVENT: Test unit PHP Event Local", $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
            $this->assertNull($return);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorInitializesLoggerFromEnvironmentVariableWhenIsLocalFalse(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=19b7c617-3d1a-4985-b751-d5d2ee96d0c8;IngestionEndpoint=');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackEventSendsToLoggerWhenLoggerClientExists(): void
    {
        $this->setUpLoggerMock();
        $eventName = 'Test unit PHP Event';
        $properties = [
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackEvent')
            ->with(
                $eventName,
                $this->callback(function($payloadTags) {
                    return $payloadTags instanceof LoggerPayloadTags
                        && is_string($payloadTags->getOperationId())
                        && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $payloadTags->getOperationId());
                }),
                $this->callback($this->containsProperties($properties))
            );

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=63765acb-8ed1-4a60-8dae-77584ed597e6;IngestionEndpoint');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackEvent($eventName, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackEventWitchPayloadWhenClientNotSetDueToIsLocalTrue(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $return = $logger->trackEvent('Test Unit PHP Event', ['foo' => 'bar']);
            $output = ob_get_clean();

            // Check that the output contains the expected event format
            $this->assertStringContainsString("INFORMATION: EVENT: Test Unit PHP Event", $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"foo":"bar"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
            $this->assertNull($return);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackExceptionSendsToClientWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $exception = new Exception('Test exception');
        $properties = [
            'Detail' => 'critical',
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackException')
            ->with($exception, $this->isInstanceOf(LoggerPayloadTags::class), $this->callback($this->containsProperties($properties)));

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=3cdd4acb-e1f2-4e1a-a88c-a9c104c7c0fd;IngestionEndpoint');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);

            $response = $logger->trackException($exception, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackExceptionEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $exception = new RuntimeException('Test runtime exception', 123);
        $properties = ['user' => 'test'];
        $formattedProperties = json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackException($exception, $properties);

            $this->assertNull($response);

            $output = ob_get_clean();

            $this->assertStringContainsString('ERROR: EXCEPTION: RuntimeException - Test runtime exception', $output);
            $this->assertStringContainsString('Properties: ' . $formattedProperties, $output);
            $this->assertStringContainsString($exception->getTraceAsString(), $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackVerboseEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $message = 'Verbose trace message';
        $properties = ['source' => 'test'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackVerbose($message, $properties);

            $this->assertNull($response);

            $output = ob_get_clean();

            // Check that the output contains the expected trace format
            $this->assertStringContainsString('VERBOSE: TRACE:', $output);
            $this->assertStringContainsString('VERBOSE - testLogger - php', $output);
            $this->assertStringContainsString('Message: Verbose trace message', $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"test"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackVerboseSendMessageWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $message = 'Verbose trace message';
        $properties = [
            'id' => 321,
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackTrace')
            ->with(
                $this->callback(function($actualMessage) use ($message) {
                    // Check if message contains the expected format with timestamp
                    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z - VERBOSE - testLogger - php - Message: ' . preg_quote($message, '/') . '$/', $actualMessage) === 1;
                }),
                SeverityLevel::VERBOSE,
                $this->isInstanceOf(LoggerPayloadTags::class),
                $this->callback($this->containsProperties($properties))
            );

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=c26e1001-c4fc-48b5-8da4-6d1fb011d5b6');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackVerbose($message, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackErrorSendMessageWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $message = 'Error trace message';
        $properties = [
            'id' => 123,
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackTrace')
            ->with(
                $this->callback(function($actualMessage) use ($message) {
                    // Check if message contains the expected format with timestamp
                    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z - ERROR - testLogger - php - Message: ' . preg_quote($message, '/') . '$/', $actualMessage) === 1;
                }),
                SeverityLevel::ERROR,
                $this->isInstanceOf(LoggerPayloadTags::class),
                $this->callback($this->containsProperties($properties))
            );

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=792f6fab-7538-4200-abfa-1fbebf2ea7db;IngestionEndpoint');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackError($message, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackErrorEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $message = 'Error trace message';
        $properties = ['source' => 'test'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackError($message, $properties);

            $this->assertNull($response);

            $output = ob_get_clean();

            // Check that the output contains the expected trace format
            $this->assertStringContainsString('ERROR: TRACE:', $output);
            $this->assertStringContainsString('ERROR - testLogger - php', $output);
            $this->assertStringContainsString('Message: Error trace message', $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"test"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackCriticalSendMessageWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $message = 'Critical trace message';
        $properties = [
            'id' => 123,
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackTrace')
            ->with(
                $this->callback(function($actualMessage) use ($message) {
                    // Check if message contains the expected format with timestamp
                    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z - CRITICAL - testLogger - php - Message: ' . preg_quote($message, '/') . '$/', $actualMessage) === 1;
                }),
                SeverityLevel::CRITICAL,
                $this->isInstanceOf(LoggerPayloadTags::class),
                $this->callback($this->containsProperties($properties))
            );

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=de612f51-d3ea-409c-bbe2-f1d42d92bdc6;IngestionEndpoint=');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackCritical($message, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackCriticalEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $message = 'Critical trace message';
        $properties = ['source' => 'test'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackCritical($message, $properties);

            $this->assertNull($response);

            $output = ob_get_clean();

            // Check that the output contains the expected trace format
            $this->assertStringContainsString('CRITICAL: TRACE:', $output);
            $this->assertStringContainsString('CRITICAL - testLogger - php', $output);
            $this->assertStringContainsString('Message: Critical trace message', $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"test"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackWarningSendMessageWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $message = 'Warning trace message';
        $properties = [
            'id' => 123,
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackTrace')
            ->with(
                $this->callback(function($actualMessage) use ($message) {
                    // Check if message contains the expected format with timestamp
                    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z - WARNING - testLogger - php - Message: ' . preg_quote($message, '/') . '$/', $actualMessage) === 1;
                }),
                SeverityLevel::WARNING,
                $this->isInstanceOf(LoggerPayloadTags::class),
                $this->callback($this->containsProperties($properties))
            );

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=232b7418-b5f6-4d17-95f1-366728ea3973;IngestionEndpoint=');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger));

            $clientProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackWarning($message, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackWarningEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $message = 'Warning trace message';
        $properties = ['source' => 'test'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackWarning($message, $properties);

            $this->assertNull($response);

            $output = ob_get_clean();

            // Check that the output contains the expected trace format
            $this->assertStringContainsString('WARNING: TRACE:', $output);
            $this->assertStringContainsString('WARNING - testLogger - php', $output);
            $this->assertStringContainsString('Message: Warning trace message', $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"test"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackTraceEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $message = 'Informational trace message';
        $properties = ['source' => 'test'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $loggerProperty = $reflection->getProperty('client');

            $this->assertNull($loggerProperty->getValue($logger));

            ob_start();
            $logger->trackInfo($message, $properties);

            $output = ob_get_clean();

            // Check that the output contains the expected trace format
            $this->assertStringContainsString('INFORMATION: TRACE:', $output);
            $this->assertStringContainsString('INFORMATION - testLogger - php', $output);
            $this->assertStringContainsString('Message: Informational trace message', $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"test"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackMetricSendsToClientWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $name = 'login_time';
        $value = 123.45;
        $properties = [
            'unit' => 'ms',
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackMetric')
            ->with($name, $value, $this->isInstanceOf(LoggerPayloadTags::class), $this->callback($this->containsProperties($properties)));

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=9b5c4eff-6af2-42cf-9d1b-f79c528503e5;IngestionEndpoint');
        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $loggerProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $loggerProperty->getValue($logger));

            $loggerProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackMetric($name, $value, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackMetricEchoesWhenClientDoesNotExistDueToIsLocalTrue(): void
    {
        $name = 'items_processed';
        $value = 100;
        $properties = ['category' => 'batch'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $loggerProperty = $reflection->getProperty('client');

            $this->assertNull($loggerProperty->getValue($logger));

            ob_start();
            $logger->trackMetric($name, $value, $properties);
            $output = ob_get_clean();

            // Check that the output contains the expected metric format
            $this->assertStringContainsString("INFORMATION: METRIC: items_processed=100", $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"category":"batch"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackRequestSendsToClientWhenClientExists(): void
    {
        $this->setUpLoggerMock();
        $name = 'API Call';
        $url = '/api/data';
        $durationMs = 14.01;
        $success = true;
        $responseCode = '200';
        $properties = [
            'user_id' => 'usr123',
            'AppServiceName' => 'testLogger',
            'ServiceName' => 'php',
            'SeverityLevel' => 'INFORMATION',
            'OperationId' => Uuid::uuid4()->toString(),
        ];

        $this->loggerMock->expects($this->once())
            ->method('trackRequest')
            ->with($name, $url, $durationMs, $success, $responseCode, $this->isInstanceOf(LoggerPayloadTags::class), $this->callback($this->containsProperties($properties)));

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=33e90835-94b3-46f3-b764-fa118eb85851;IngestionEndpoint');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $loggerProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $loggerProperty->getValue($logger));

            $loggerProperty->setValue($logger, $this->loggerMock);
            $response = $logger->trackRequest($name, $url, $durationMs, $success, $responseCode, $properties);

            $this->assertNull($response);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackRequestEchoesWhenLoggerDoesNotExistDueToIsLocalTrue(): void
    {
        $name = 'Page Load';
        $url = '/home';
        $durationMs = 150;
        $success = false;
        $responseCode = '500';
        $properties = ['source' => 'web'];

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {

            $formattedProperties = json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackRequest($name, $url, $durationMs, $success, $responseCode, $properties);

            $this->assertNull($response);
            $output = ob_get_clean();

            // Check that the output contains the expected request format
            $this->assertStringContainsString("INFORMATION: REQUEST: Page Load [/home], Duration: 150 ms, Success: , ResponseCode: 500", $output);
            // Check that the output contains the original properties
            $this->assertStringContainsString('"source":"web"', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testFormatPropertiesHandlesEmptyArray(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            ob_start();
            $response = $logger->trackEvent('Test Unit Event PHP No Properties');

            $this->assertNull($response);
            $output = ob_get_clean();
            
            // Check that the output contains the expected event format
            $this->assertStringContainsString('INFORMATION: EVENT: Test Unit Event PHP No Properties', $output);
            // Check that the output contains the automatically added properties (no longer empty array)
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testFormatPropertiesHandlesSpecialCharacters(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger));

            $properties = ['description' => 'Text with /slashes/ and "quotes" and unicode éàçüö'];
            ob_start();
            $response = $logger->trackEvent('Special Chars Event', $properties);

            $this->assertNull($response);
            $output = ob_get_clean();
            
            // Check that the output contains the expected event format
            $this->assertStringContainsString('INFORMATION: EVENT: Special Chars Event', $output);
            // Check that the output contains the original properties with special characters (JSON escaped)
            $this->assertStringContainsString('Text with /slashes/ and \\"quotes\\" and unicode éàçüö', $output);
            // Check that the output contains the automatically added properties
            $this->assertStringContainsString('"AppServiceName":"testLogger"', $output);
            $this->assertStringContainsString('"ServiceName":"php"', $output);
            $this->assertStringContainsString('"OperationId":', $output);
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorDoesNotInitializeClientWhenIsLocalTrueAndKeyExistsInEnv(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger), 'Client should not be initialized when APPLICATIONINSIGHTS_CONNECTION_STRING is not set.');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorInitializesClientWhenIsLocalFalseAndKeyExistsInEnv(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=7f2aa79d-9f33-4e92-ae1c-7af3faab32c1;IngestionEndpoint=');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger), 'Client should be initialized when isLocal is false and APPLICATIONINSIGHTS_CONNECTION_STRING is set.');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorDoesNotInitializeClientWhenIsLocalFalseAndKeyNotExistsInEnv(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger), 'Client should not be initialized when isLocal is false and APPLICATIONINSIGHTS_CONNECTION_STRING is not set.');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorUsesDefaultIsLocalFalseWhenNoArgumentProvidedAndKeyExistsInEnv(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=00a7e707-e1c2-46f8-a1a5-50dd25a874af;IngestionEndpoint=');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertInstanceOf(LoggerApiClientInterface::class, $clientProperty->getValue($logger), 'Client should be initialized with default isLocal=false when APPLICATIONINSIGHTS_CONNECTION_STRING is set.');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testConstructorUsesDefaultIsLocalFalseWhenNoArgumentProvidedAndKeyNotExistsInEnv(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');

        try {
            $logger = new Logger($this->logger);
            $this->assertInstanceOf(LoggerInterface::class, $logger);

            $reflection = new ReflectionClass($logger);
            $clientProperty = $reflection->getProperty('client');

            $this->assertNull($clientProperty->getValue($logger), 'Client should not be initialized with default isLocal=false when APPLICATIONINSIGHTS_CONNECTION_STRING is not set.');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackEventDoesNotWriteToStdoutWhenClientIsConfigured(): void
    {
        $this->setUpLoggerMock();
        $this->loggerMock->expects($this->once())->method('trackEvent');

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');
        try {
            $logger = new Logger($this->logger);
            $clientProperty = (new ReflectionClass($logger))->getProperty('client');
            $clientProperty->setValue($logger, $this->loggerMock);

            ob_start();
            $logger->trackEvent('Event name', ['key' => 'value']);
            $output = ob_get_clean();

            $this->assertSame('', $output, 'Configured telemetry client must not trigger stdout fallback');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackVerboseDoesNotWriteToStdoutWhenClientIsConfigured(): void
    {
        $this->setUpLoggerMock();
        $this->loggerMock->expects($this->once())->method('trackTrace');

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');
        try {
            $logger = new Logger($this->logger);
            $clientProperty = (new ReflectionClass($logger))->getProperty('client');
            $clientProperty->setValue($logger, $this->loggerMock);

            ob_start();
            $logger->trackVerbose('verbose step', ['i' => 1]);
            $output = ob_get_clean();

            $this->assertSame('', $output, 'Configured telemetry client must not trigger stdout fallback for verbose traces');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testTrackExceptionDoesNotWriteToStdoutWhenClientIsConfigured(): void
    {
        $this->setUpLoggerMock();
        $this->loggerMock->expects($this->once())->method('trackException');

        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');
        try {
            $logger = new Logger($this->logger);
            $clientProperty = (new ReflectionClass($logger))->getProperty('client');
            $clientProperty->setValue($logger, $this->loggerMock);

            ob_start();
            $logger->trackException(new RuntimeException('test'), ['detail' => 'x']);
            $output = ob_get_clean();

            $this->assertSame('', $output, 'Configured telemetry client must not duplicate exception output to stdout');
        } finally {
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    #[Group('unit')]
    public function testRealLoggerApiClientDoesNotWriteToStdoutForTrackInfo(): void
    {
        $existingKey = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=67ba5e7d-400f-4aaa-94ea-775f3c02d4fd');
        try {
            TelemetryChannel::reset();
            $logger = new Logger($this->logger);

            ob_start();
            $logger->trackInfo('production path', ['step' => 1]);
            $output = ob_get_clean();

            $this->assertSame('', $output, 'Real Application Insights client must not trigger stdout fallback');
        } finally {
            TelemetryChannel::reset();
            if ($existingKey !== false) {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $existingKey);
            } else {
                putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
            }
        }
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function containsProperties(array $expected): callable
    {
        return static function (array $actual) use ($expected): bool {
            foreach ($expected as $key => $value) {
                if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
                    return false;
                }
            }

            return array_key_exists('TraceId', $actual) && is_string($actual['TraceId']) && $actual['TraceId'] !== '';
        };
    }
}
