<?php

declare(strict_types=1);

namespace Unit;

use DateMalformedStringException;
use Exception;
use GraftCodeLogger\Logger\LoggerApiClient\LoggerApiClient;
use GraftCodeLogger\Telemetry\TelemetryChannel;
use GraftCodeLogger\Utils\Enum\SeverityLevel;
use GraftCodeLogger\Utils\Uuid;
use GraftCodeLogger\Utils\VO\LoggerPayloadTags;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

final class LoggerApiClientTest extends TestCase
{
    private const string FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING = 'test-ikey';

    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>, response?: Response, error?: array<string, mixed>}> */
    private array $historyContainer = [];

    protected function setUp(): void
    {
        TelemetryChannel::reset();
        $this->historyContainer = [];
    }

    protected function tearDown(): void
    {
        TelemetryChannel::reset();
    }

    private function createClientWithMockedHttpClient(array $responses): LoggerApiClient
    {
        $mock = new MockHandler($responses);
        $this->historyContainer = [];
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($this->recordHistory($this->historyContainer));

        $httpClient = new GuzzleClient([
            'handler' => $handlerStack,
            'http_errors' => false
        ]);

        TelemetryChannel::instance()->setHttpClient($httpClient);

        return new LoggerApiClient(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentEnvelope(): array
    {
        TelemetryChannel::instance()->flush();
        $this->assertCount(1, $this->historyContainer);
        $payload = json_decode((string) $this->historyContainer[0]['request']->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey(0, $payload);

        return $payload[0];
    }

    #[Group('unit')]
    private function recordHistory(array &$container): callable
    {
        return function (callable $handler) use (&$container) {
            return function (RequestInterface $request, array $options) use ($handler, &$container) {
                $promise = $handler($request, $options);
                return $promise->then(
                    function ($response) use ($request, $options, &$container) {
                        $container[] = ['request' => $request, 'options' => $options, 'response' => $response];
                        return $response;
                    },
                    function ($reason) use ($request, $options, &$container) {
                        $container[] = ['request' => $request, 'options' => $options, 'error' => $reason];
                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    #[Group('unit')]
    public function testConstructorSetsInstrumentationKey(): void
    {
        $client = new LoggerApiClient(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING);
        $reflection = new ReflectionClass($client);
        $instrumentationKeyProperty = $reflection->getProperty('instrumentationKey');

        $this->assertSame(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING, $instrumentationKeyProperty->getValue($client));
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackTraceSendsCorrectPayload(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $message = 'Test trace message';
        $severity = SeverityLevel::WARNING;
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());
        $properties = ['client_unit_test_track_trace' => 'test_value1'];

        $client->trackTrace($message, $severity, $payloadTags, $properties);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();

        $this->assertSame('Microsoft.ApplicationInsights.Message', $payload['name']);
        $this->assertSame(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING, $payload['iKey']);
        $this->assertArrayHasKey('time', $payload);
        $this->assertSame('MessageData', $payload['data']['baseType']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame($message, $payload['data']['baseData']['message']);
        $this->assertSame($severity->value, $payload['data']['baseData']['severityLevel']);
        $this->assertSame($properties, $payload['data']['baseData']['properties']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackMetricSendsCorrectPayload(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $name = 'test_metric';
        $value = 123.45;
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());
        $properties = ['client_unit_test_metric' => 'ms'];

        $client->trackMetric($name, $value, $payloadTags, $properties);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();

        $this->assertSame('Microsoft.ApplicationInsights.Metric', $payload['name']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING, $payload['iKey']);
        $this->assertSame('MetricData', $payload['data']['baseType']);
        $this->assertCount(1, $payload['data']['baseData']['metrics']);
        $this->assertSame($name, $payload['data']['baseData']['metrics'][0]['name']);
        $this->assertSame($value, $payload['data']['baseData']['metrics'][0]['value']);
        $this->assertSame($properties, $payload['data']['baseData']['properties']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackRequestSendsCorrectPayload(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $name = 'GET /test';
        $url = 'https://example.com/test';
        $durationMs = 150;
        $success = true;
        $responseCode = 200;
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());
        $properties = ['client_unit_test_track_request' => 5];

        $client->trackRequest($name, $url, $durationMs, $success, $responseCode, $payloadTags, $properties);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();

        $this->assertSame('Microsoft.ApplicationInsights.Request', $payload['name']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame($name, $payload['data']['baseData']['name']);
        $this->assertSame($url, $payload['data']['baseData']['url']);
        $this->assertSame('0.00:00:00.150000', $payload['data']['baseData']['duration']);
        $this->assertSame((string)$responseCode, $payload['data']['baseData']['responseCode']);
        $this->assertSame($success, $payload['data']['baseData']['success']);
        $this->assertSame($properties, $payload['data']['baseData']['properties']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackEventSendsCorrectPayload(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $name = 'user_login';
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());
        $properties = ['client_unit_test_track_event' => 'form'];

        $client->trackEvent($name, $payloadTags, $properties);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();

        $this->assertSame('Microsoft.ApplicationInsights.Event', $payload['name']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame('EventData', $payload['data']['baseType']);
        $this->assertSame($name, $payload['data']['baseData']['name']);
        $this->assertSame($properties, $payload['data']['baseData']['properties']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackExceptionSendsCorrectPayload(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $exceptionMessage = 'Test runtime exception';
        $exceptionCode = 123;
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());
        $properties = ['test_track_exception_extra_info' => 'critical'];
        $testException = new RuntimeException($exceptionMessage, $exceptionCode);

        $client->trackException($testException, $payloadTags, $properties);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();

        $this->assertSame('Microsoft.ApplicationInsights.Exception', $payload['name']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame('ExceptionData', $payload['data']['baseType']);
        $this->assertCount(1, $payload['data']['baseData']['exceptions']);
        $exceptionData = $payload['data']['baseData']['exceptions'][0];

        $this->assertSame(get_class($testException), $exceptionData['typeName']);
        $this->assertSame($exceptionMessage, $exceptionData['message']);
        $this->assertTrue($exceptionData['hasFullStack']);
        $this->assertIsArray($exceptionData['parsedStack']);
        $this->assertNotEmpty($exceptionData['parsedStack']);
        $this->assertSame($properties, $payload['data']['baseData']['properties']);
    }

    /**
     * @throws ReflectionException
     */
    #[Group('unit')]
    #[DataProvider('baseTypeNameProvider')]
    public function testGetBaseTypeName(string $type, string $expectedBaseTypeName): void
    {
        $client = new LoggerApiClient(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING);
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getBaseTypeName');

        $this->assertSame($expectedBaseTypeName, $method->invoke($client, $type));
    }

    public static function baseTypeNameProvider(): array
    {
        return [
            'Message' => ['Microsoft.ApplicationInsights.Message', 'MessageData'],
            'Metric' => ['Microsoft.ApplicationInsights.Metric', 'MetricData'],
            'Request' => ['Microsoft.ApplicationInsights.Request', 'RequestData'],
            'Event' => ['Microsoft.ApplicationInsights.Event', 'EventData'],
            'Exception' => ['Microsoft.ApplicationInsights.Exception', 'ExceptionData'],
            'SingleName' => ['CustomType', 'CustomTypeData'],
        ];
    }

    #[Group('unit')]
    public function testParseStackTrace(): void
    {
        $client = new LoggerApiClient(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING);
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('parseStackTrace');

        try {
            throw new LogicException('Test for stack trace', 500);
        } catch (LogicException $exception) {
            $parsedStack = $method->invoke($client, $exception);

            $this->assertIsArray($parsedStack);
            $this->assertNotEmpty($parsedStack);

            $this->assertTrue(
                array_reduce($parsedStack, fn($carry, $frame) => $carry || (isset($frame['fileName']) && str_contains($frame['fileName'], 'LoggerApiClientTest.php')), false),
                'The stack trace does not include LoggerApiClientTest.php'
            );

            $firstFrame = $parsedStack[0];
            $this->assertSame(0, $firstFrame['level']);
            $this->assertSame('{main}', $firstFrame['method']);
            $this->assertSame('PHP Application', $firstFrame['assembly']);
            $this->assertSame($exception->getLine(), $firstFrame['line']);

            if (count($parsedStack) > 1) {
                $secondFrame = $parsedStack[1];
                $this->assertSame(1, $secondFrame['level']);
                $this->assertStringContainsString(__CLASS__ . '->' . __FUNCTION__, $secondFrame['method']);

                $found = false;
                foreach ($parsedStack as $frame) {
                    if (isset($frame['fileName']) && str_contains($frame['fileName'], 'LoggerApiClientTest.php')) {
                        $found = true;
                        break;
                    }
                }
                $this->assertTrue($found, 'The stack trace does not contain LoggerApiClientTest.php in any frame.');
            }
        }
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsSuccess(): void
    {
        $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $testPayload = [
            'name' => 'Microsoft.ApplicationInsights.Message',
            'time' => '2024-01-01T00:00:00.000Z',
            'iKey' => self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING,
            'tags' => ['ai.operation.id' => $payloadTags->getOperationId()],
            'data' => [
                'baseType' => 'MessageData',
                'baseData' => [
                    'message' => 'Test direct call to sendToApplicationInsights',
                    'severityLevel' => 'INFORMATION',
                    'properties' => (object) ['test' => 'value']
                ]
            ]
        ];

        TelemetryChannel::instance()->enqueue($testPayload);
        $payload = $this->sentEnvelope();
        $sentRequest = $this->historyContainer[0]['request'];
        $this->assertSame('POST', $sentRequest->getMethod());
        $this->assertSame('https://dc.services.visualstudio.com/v2/track', (string) $sentRequest->getUri());
        $this->assertSame('application/json', $sentRequest->getHeaderLine('Content-Type'));

        $this->assertSame('Microsoft.ApplicationInsights.Message', $payload['name']);
        $this->assertSame(self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING, $payload['iKey']);
        $this->assertSame($payloadTags->getOperationId(), $payload['tags']['ai.operation.id']);
        $this->assertSame('Test direct call to sendToApplicationInsights', $payload['data']['baseData']['message']);
        $this->assertSame('INFORMATION', $payload['data']['baseData']['severityLevel']);
        $this->assertSame('value', $payload['data']['baseData']['properties']['test']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackTraceWithEmptyMessage(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackTrace('', SeverityLevel::WARNING, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('', $payload['data']['baseData']['message']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackMetricWithStringValue(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackMetric('string_metric', '123.45', $payloadTags);
        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('123.45', $payload['data']['baseData']['metrics'][0]['value']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackRequestWithInvalidUrl(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackRequest('Test Request', 'not-a-valid-url', 100, true, 200, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('not-a-valid-url', $payload['data']['baseData']['url']);
    }

    #[Group('unit')]
    public function testTrackExceptionWithSimpleException(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $exception = new Exception('Test exception message');

        $client->trackException($exception, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('Test exception message', $payload['data']['baseData']['exceptions'][0]['message']);
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithHttpError4xx(): void
    {
        $this->createClientWithMockedHttpClient([
            new Response(400, [], 'Bad Request')
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertSame(1, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
        $this->assertSame(400, $this->historyContainer[0]['response']->getStatusCode());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithHttpError5xx(): void
    {
        $this->createClientWithMockedHttpClient([
            new Response(500, [], 'Internal Server Error')
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertSame(1, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame(500, $this->historyContainer[0]['response']->getStatusCode());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithHttpError202(): void
    {
        $this->createClientWithMockedHttpClient([
            new Response(202, [], 'Accepted')
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertSame(0, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame(202, $this->historyContainer[0]['response']->getStatusCode());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithRequestException(): void
    {
        $this->createClientWithMockedHttpClient([
            new RequestException(
                'Request failed',
                new Request('POST', 'https://example.com'),
                new Response(404, [], 'Not Found')
            )
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertGreaterThan(0, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame(0, TelemetryChannel::instance()->pendingCount());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithGuzzleException(): void
    {
        $this->createClientWithMockedHttpClient([
            new ConnectException(
                'Connection failed',
                new Request('POST', 'https://example.com')
            )
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertGreaterThan(0, TelemetryChannel::instance()->flushFailureCount());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithUnexpectedException(): void
    {
        $this->createClientWithMockedHttpClient([
            new Exception('Unexpected error')
        ]);

        TelemetryChannel::instance()->enqueue($this->sampleEnvelope());
        TelemetryChannel::instance()->flush();

        $this->assertGreaterThan(0, TelemetryChannel::instance()->flushFailureCount());
    }

    #[Group('unit')]
    public function testSendToApplicationInsightsWithJsonEncodingError(): void
    {
        $resource = fopen('php://temp', 'r');
        $testPayload = [
            'name' => 'Microsoft.ApplicationInsights.Message',
            'time' => '2024-01-01T00:00:00.000Z',
            'iKey' => self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING,
            'tags' => ['ai.operation.id' => 'op'],
            'data' => ['baseType' => 'MessageData', 'baseData' => ['resource' => $resource]]
        ];

        TelemetryChannel::instance()->enqueue($testPayload);
        TelemetryChannel::instance()->flush();

        $this->assertGreaterThan(0, TelemetryChannel::instance()->flushFailureCount());
        $this->assertSame([], $this->historyContainer);
        fclose($resource);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleEnvelope(): array
    {
        return [
            'name' => 'Microsoft.ApplicationInsights.Message',
            'time' => '2024-01-01T00:00:00.000Z',
            'iKey' => self::FAKE_APPLICATIONINSIGHTS_CONNECTION_STRING,
            'tags' => ['ai.operation.id' => 'op'],
            'data' => ['baseType' => 'MessageData', 'baseData' => ['message' => 'Test']],
        ];
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackExceptionWithComplexStackTrace(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $exception = new Exception('Complex exception');

        $reflection = new ReflectionClass($exception);
        $traceProperty = $reflection->getProperty('trace');
        $traceProperty->setValue($exception, [
            [
                'class' => 'TestClass',
                'type' => '->',
                'function' => 'testMethod',
                'file' => '/path/to/file.php',
                'line' => 123,
                'args' => ['arg1', 'arg2']
            ],
            [
                'function' => 'globalFunction',
                'file' => '/path/to/global.php',
                'line' => 456
            ],
            [
                'class' => 'StaticClass',
                'type' => '::',
                'function' => 'staticMethod',
                'file' => '/path/to/static.php',
                'line' => 789
            ]
        ]);
        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackException($exception, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $parsedStack = $payload['data']['baseData']['exceptions'][0]['parsedStack'];

        $this->assertCount(4, $parsedStack);
        $this->assertSame('TestClass->testMethod', $parsedStack[1]['method']);
        $this->assertSame('globalFunction', $parsedStack[2]['method']);
        $this->assertSame('StaticClass::staticMethod', $parsedStack[3]['method']);
    }

    #[Group('unit')]
    public function testTrackExceptionWithEmptyStackTrace(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $exception = new Exception('Empty stack trace');

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $reflection = new ReflectionClass($exception);
        $traceProperty = $reflection->getProperty('trace');
        $traceProperty->setValue($exception, []);

        $client->trackException($exception, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $parsedStack = $payload['data']['baseData']['exceptions'][0]['parsedStack'];

        $this->assertCount(1, $parsedStack);
        $this->assertSame('{main}', $parsedStack[0]['method']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackExceptionWithInvalidStackTraceFrame(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $exception = new Exception('Invalid stack trace');

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $reflection = new ReflectionClass($exception);
        $traceProperty = $reflection->getProperty('trace');
        $traceProperty->setValue($exception, [
            [
                'invalid_key' => 'invalid_value',
            ],
            [
                'class' => null,
                'type' => null,
                'function' => null,
                'file' => null,
                'line' => null
            ]
        ]);

        $client->trackException($exception, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $parsedStack = $payload['data']['baseData']['exceptions'][0]['parsedStack'];

        $this->assertCount(3, $parsedStack);
        $this->assertSame('[internal function]', $parsedStack[1]['method']);
        $this->assertSame('[internal function]', $parsedStack[2]['method']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackRequestWithNegativeDuration(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackRequest('Test Request', 'https://example.com', -100, true, 200, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('0.00:00:00.000000', $payload['data']['baseData']['duration']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackRequestWithZeroDuration(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackRequest('Test Request', 'https://example.com', 0, true, 200, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('0.00:00:00.000000', $payload['data']['baseData']['duration']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackRequestWithStringResponseCode(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackRequest('Test Request', 'https://example.com', 100, true, '200 OK', $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('200 OK', $payload['data']['baseData']['responseCode']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackEventWithEmptyName(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackEvent('', $payloadTags, ['test' => 'value']);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('', $payload['data']['baseData']['name']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackEventWithNullProperties(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackEvent('test_event', $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertEquals([], $payload['data']['baseData']['properties']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackMetricWithEmptyName(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackMetric('', 123.45, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame('', $payload['data']['baseData']['metrics'][0]['name']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackMetricWithZeroValue(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackMetric('zero_metric', 0, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame(0, $payload['data']['baseData']['metrics'][0]['value']);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Group('unit')]
    public function testTrackMetricWithNegativeValue(): void
    {
        $client = $this->createClientWithMockedHttpClient([
            new Response(200, [], 'Success')
        ]);

        $payloadTags = new LoggerPayloadTags(Uuid::uuid4());

        $client->trackMetric('negative_metric', -123.45, $payloadTags);

        $this->assertGreaterThan(0, TelemetryChannel::instance()->pendingCount());
        $payload = $this->sentEnvelope();
        $this->assertSame(-123.45, $payload['data']['baseData']['metrics'][0]['value']);
    }
}
