<?php

declare(strict_types=1);

namespace Tests\Unit;

use GraftCodeLogger\Utils\Config\EnvironmentConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class EnvironmentConfigTest extends TestCase
{
    private ?string $originalConnectionString = null;

    protected function setUp(): void
    {
        // Store original environment variable
        $this->originalConnectionString = getenv('APPLICATIONINSIGHTS_CONNECTION_STRING') ?: null;
    }

    protected function tearDown(): void
    {
        // Restore original environment variable
        if ($this->originalConnectionString !== null) {
            putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $this->originalConnectionString);
        } else {
            putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        }
    }

    public static function invalidEnvironmentVariableProvider(): array
    {
        return [
            'environment variable not set' => [null],
            'environment variable empty' => [''],
            'environment variable whitespace' => ['   '],
            'invalid connection string' => ['invalid-connection-string'],
            'missing instrumentation key' => ['IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/'],
            'empty instrumentation key' => ['InstrumentationKey=;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/'],
            'whitespace instrumentation key' => ['InstrumentationKey=   ;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/'],
            'instrumentation key parsed as array' => [
                'InstrumentationKey[]=value;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/',
            ],
            'instrumentation key parsed as nested array' => [
                'InstrumentationKey[foo]=bar;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/',
            ],
        ];
    }

    public static function invalidUuidProvider(): array
    {
        return [
            'invalid uuid format' => ['invalid-uuid'],
            'uuid v1' => ['12345678-1234-1234-1234-123456789012'],
            'uuid v2' => ['12345678-1234-2234-1234-123456789012'],
            'uuid v3' => ['12345678-1234-3234-1234-123456789012'],
            'uuid v5' => ['12345678-1234-5234-1234-123456789012'],
            'uuid v6' => ['12345678-1234-6234-1234-123456789012'],
            'uuid v7' => ['12345678-1234-7234-1234-123456789012'],
            'uuid v8' => ['12345678-1234-8234-1234-123456789012'],
            'uuid v9' => ['12345678-1234-9234-1234-123456789012'],
            'uuid v10' => ['12345678-1234-a234-1234-123456789012'],
            'uuid v11' => ['12345678-1234-b234-1234-123456789012'],
            'uuid v12' => ['12345678-1234-c234-1234-123456789012'],
            'uuid v13' => ['12345678-1234-d234-1234-123456789012'],
            'uuid v14' => ['12345678-1234-e234-1234-123456789012'],
            'uuid v15' => ['12345678-1234-f234-1234-123456789012'],
            'wrong variant c' => ['12345678-1234-4567-c901-123456789012'],
            'wrong variant d' => ['12345678-1234-4567-d901-123456789012'],
            'wrong variant e' => ['12345678-1234-4567-e901-123456789012'],
            'wrong variant f' => ['12345678-1234-4567-f901-123456789012'],
        ];
    }

    public static function validUuidProvider(): array
    {
        return [
            'standard format' => ['12345678-1234-4567-8901-123456789012'],
            'with letters' => ['abcdef12-3456-4567-8901-123456789012'],
            'all zeros with valid v4 bits' => ['00000000-0000-4000-8000-000000000000'],
            'all f\'s with valid v4 bits' => ['ffffffff-ffff-4fff-bfff-ffffffffffff'],
            'uppercase uuid' => ['12345678-1234-4567-8901-123456789012', '12345678-1234-4567-8901-123456789012'],
        ];
    }

    public static function validConnectionStringProvider(): array
    {
        return [
            'basic connection string' => [
                'InstrumentationKey=12345678-1234-4567-8901-123456789012;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/',
                '12345678-1234-4567-8901-123456789012'
            ],
            'multiple parameters' => [
                'InstrumentationKey=12345678-1234-4567-8901-123456789012;IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/;LiveEndpoint=https://westeurope-5.livediagnostics.monitor.azure.com/',
                '12345678-1234-4567-8901-123456789012'
            ],
            'different separators' => [
                'InstrumentationKey=12345678-1234-4567-8901-123456789012&IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/',
                '12345678-1234-4567-8901-123456789012'
            ],
        ];
    }

    #[Group('unit')]
    #[DataProvider('invalidEnvironmentVariableProvider')]
    public function testGetInstrumentationKeyReturnsNullForInvalidEnvironmentVariables(?string $connectionString): void
    {
        if ($connectionString === null) {
            putenv('APPLICATIONINSIGHTS_CONNECTION_STRING');
        } else {
            putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $connectionString);
        }

        $result = EnvironmentConfig::getInstrumentationKey();

        $this->assertNull($result);
    }

    #[Group('unit')]
    #[DataProvider('invalidUuidProvider')]
    public function testGetInstrumentationKeyReturnsNullForInvalidUuids(string $uuid): void
    {
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=' . $uuid . ';IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/');

        $result = EnvironmentConfig::getInstrumentationKey();

        $this->assertNull($result, "Should return null for invalid UUID: $uuid");
    }

    #[Group('unit')]
    #[DataProvider('validUuidProvider')]
    public function testGetInstrumentationKeyReturnsValidKeyForValidUuids(string $uuid, ?string $expectedUuid = null): void
    {
        $expectedUuid = $expectedUuid ?? $uuid;
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=InstrumentationKey=' . $uuid . ';IngestionEndpoint=https://westeurope-5.in.applicationinsights.azure.com/');

        $result = EnvironmentConfig::getInstrumentationKey();

        $this->assertEquals($expectedUuid, $result, "Failed for UUID: $uuid");
    }

    #[Group('unit')]
    #[DataProvider('validConnectionStringProvider')]
    public function testGetInstrumentationKeyReturnsValidKeyForValidConnectionStrings(string $connectionString, string $expectedUuid): void
    {
        putenv('APPLICATIONINSIGHTS_CONNECTION_STRING=' . $connectionString);

        $result = EnvironmentConfig::getInstrumentationKey();

        $this->assertEquals($expectedUuid, $result);
    }
}
