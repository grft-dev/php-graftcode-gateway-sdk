<?php

declare(strict_types=1);

namespace Unit;

use GraftcodeStorage\Utils\{
    Config\EnvironmentConfig,
    Enum\StorageType,
    Exception\Storage\EmptyAzureStorageEnvironmentException
};
use PHPUnit\Framework\{
    Attributes\DataProvider,
    Attributes\Group,
    TestCase
};
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final class EnvironmentConfigTest extends TestCase
{
    private array $originalEnvVars = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvVars = [
            'STORAGE_TYPE' => getenv('STORAGE_TYPE'),
            'AZURE_STORAGE_URL' => getenv('AZURE_STORAGE_URL'),
            'AZURE_STORAGE_TOKEN' => getenv('AZURE_STORAGE_TOKEN'),
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreEnvironmentVariables();
        } finally {
            parent::tearDown();
        }
    }

    private function restoreEnvironmentVariables(): void
    {
        foreach ($this->originalEnvVars as $key => $value) {
            if ($value !== false) {
                putenv($key . '=' . $value);
            } else {
                putenv($key);
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function setEnvironmentVariableSafely(string $key, ?string $value = null): void
    {
        try {
            if ($value === null) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        } catch (Throwable $e) {
            $this->restoreEnvironmentVariables();
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetStorageTypeReturnsAzureWhenStorageTypeNotSet(): void
    {
        $this->setEnvironmentVariableSafely('STORAGE_TYPE');

        $result = EnvironmentConfig::getStorageType();

        $this->assertEquals(StorageType::AZURE->name, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetStorageTypeReturnsAzureWhenStorageTypeIsEmpty(): void
    {
        $this->setEnvironmentVariableSafely('STORAGE_TYPE', '');

        $result = EnvironmentConfig::getStorageType();

        $this->assertEquals(StorageType::AZURE->name, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetStorageTypeReturnsAzureWhenStorageTypeIsWhitespace(): void
    {
        $this->setEnvironmentVariableSafely('STORAGE_TYPE', '   ');

        $result = EnvironmentConfig::getStorageType();

        $this->assertEquals(StorageType::AZURE->name, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    #[DataProvider('validStorageTypeProvider')]
    public function testGetStorageTypeReturnsSetValue(string $storageType): void
    {
        $this->setEnvironmentVariableSafely('STORAGE_TYPE', $storageType);

        $result = EnvironmentConfig::getStorageType();

        $this->assertEquals($storageType, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageUrlReturnsValidUrl(): void
    {
        $expectedUrl = 'https://example.blob.core.windows.net/';
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_URL', $expectedUrl);

        $result = EnvironmentConfig::getAzureStorageUrl();

        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageUrlThrowsExceptionWhenNotSet(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_URL');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_URL environment variable is not set');

        EnvironmentConfig::getAzureStorageUrl();
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageUrlThrowsExceptionWhenEmpty(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_URL', '');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_URL environment variable is not set');

        EnvironmentConfig::getAzureStorageUrl();
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageUrlThrowsExceptionWhenWhitespace(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_URL', '   ');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_URL environment variable is not set');

        EnvironmentConfig::getAzureStorageUrl();
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageTokenReturnsValidToken(): void
    {
        $expectedToken = 'sp=r&st=2023-01-01T00:00:00Z&se=2024-01-01T00:00:00Z&sv=2022-11-02&sr=c&sig=example';
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_TOKEN', $expectedToken);

        $result = EnvironmentConfig::getAzureStorageToken();

        $this->assertEquals($expectedToken, $result);
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageTokenThrowsExceptionWhenNotSet(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_TOKEN');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_TOKEN environment variable is not set');

        EnvironmentConfig::getAzureStorageToken();
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageTokenThrowsExceptionWhenEmpty(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_TOKEN', '');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_TOKEN environment variable is not set');

        EnvironmentConfig::getAzureStorageToken();
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testGetAzureStorageTokenThrowsExceptionWhenWhitespace(): void
    {
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_TOKEN', '   ');

        $this->expectException(EmptyAzureStorageEnvironmentException::class);
        $this->expectExceptionMessage('AZURE_STORAGE_TOKEN environment variable is not set');

        EnvironmentConfig::getAzureStorageToken();
    }

    #[Group('unit')]
    #[DataProvider('azureUrlProvider')]
    public function testGetAzureStorageUrlWithVariousValidUrls(string $url): void
    {
        putenv('AZURE_STORAGE_URL=' . $url);

        $result = EnvironmentConfig::getAzureStorageUrl();

        $this->assertEquals($url, $result);
    }

    #[Group('unit')]
    #[DataProvider('azureTokenProvider')]
    public function testGetAzureStorageTokenWithVariousValidTokens(string $token): void
    {
        putenv('AZURE_STORAGE_TOKEN=' . $token);

        $result = EnvironmentConfig::getAzureStorageToken();

        $this->assertEquals($token, $result);
    }

    #[Group('unit')]
    public function testEnvironmentConfigIsReadonly(): void
    {
        $reflection = new ReflectionClass(EnvironmentConfig::class);

        $this->assertTrue($reflection->isReadOnly(), 'EnvironmentConfig class should be readonly');
    }

    #[Group('unit')]
    public function testEnvironmentConfigIsFinal(): void
    {
        $reflection = new ReflectionClass(EnvironmentConfig::class);

        $this->assertTrue($reflection->isFinal(), 'EnvironmentConfig class should be final');
    }

    #[Group('unit')]
    public function testEnvironmentConfigHasPrivateConstructor(): void
    {
        $reflection = new ReflectionClass(EnvironmentConfig::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, 'EnvironmentConfig should have a constructor');
        $this->assertTrue($constructor->isPrivate(), 'EnvironmentConfig constructor should be private');
    }

    #[Group('unit')]
    public function testAllPublicMethodsAreStatic(): void
    {
        $reflection = new ReflectionClass(EnvironmentConfig::class);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            $this->assertTrue(
                $method->isStatic(),
                'Method ' . $method->getName() . 'should be static'
            );
        }
    }

    #[Group('unit')]
    public function testOnlyExpectedPublicMethodsExist(): void
    {
        $reflection = new ReflectionClass(EnvironmentConfig::class);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        $expectedMethods = ['getStorageType', 'getAzureStorageUrl', 'getAzureStorageToken'];
        $actualMethods = array_map(fn($method) => $method->getName(), $publicMethods);

        sort($expectedMethods);
        sort($actualMethods);

        $this->assertEquals(
            $expectedMethods,
            $actualMethods,
            'EnvironmentConfig should only have expected public methods'
        );
    }

    /**
     * @throws Throwable
     */
    #[Group('unit')]
    public function testEnvironmentVariablesAreRestoredAfterTest(): void
    {
        $this->setEnvironmentVariableSafely('STORAGE_TYPE', 'TEST_TYPE');
        $this->setEnvironmentVariableSafely('AZURE_STORAGE_URL', 'https://test.example.com');

        $this->assertEquals('TEST_TYPE', getenv('STORAGE_TYPE'));
        $this->assertEquals('https://test.example.com', getenv('AZURE_STORAGE_URL'));
    }

    #[Group('unit')]
    public function testEnvironmentVariablesWereRestoredFromPreviousTest(): void
    {
        $currentStorageType = getenv('STORAGE_TYPE');
        $currentAzureUrl = getenv('AZURE_STORAGE_URL');

        $this->assertNotEquals('TEST_TYPE', $currentStorageType);
        $this->assertNotEquals('https://test.example.com', $currentAzureUrl);

        $this->assertEquals($this->originalEnvVars['STORAGE_TYPE'], $currentStorageType);
        $this->assertEquals($this->originalEnvVars['AZURE_STORAGE_URL'], $currentAzureUrl);
    }

    public static function validStorageTypeProvider(): \Generator
    {
        yield 'LOCAL storage type' => ['LOCAL'];
        yield 'AZURE storage type' => ['AZURE'];
        yield 'STORAGE_TYPE storage type' => ['STORAGE_TYPE'];
        yield 'Custom storage type' => ['CUSTOM'];
        yield 'Lowercase storage type' => ['local'];
        yield 'Mixed case storage type' => ['Azure'];
        yield 'Storage type with numbers' => ['STORAGE_V2'];
        yield 'Storage type with underscores' => ['MY_STORAGE_TYPE'];
    }

    public static function azureUrlProvider(): \Generator
    {
        yield 'Standard Azure URL' => ['https://myaccount.blob.core.windows.net/'];
        yield 'Azure URL with container' => ['https://myaccount.blob.core.windows.net/container'];
        yield 'Azure URL with path' => ['https://myaccount.blob.core.windows.net/container/path'];
        yield 'Azure URL with query params' => ['https://myaccount.blob.core.windows.net/?param=value'];
        yield 'Azure URL with port' => ['https://myaccount.blob.core.windows.net:443/'];
        yield 'Local development URL' => ['https://127.0.0.1:10000/devstoreaccount1'];
        yield 'URL with special characters' => ['https://my-account.blob.core.windows.net/my-container'];
    }

    public static function azureTokenProvider(): \Generator
    {
        yield 'SAS token' => ['sp=r&st=2023-01-01T00:00:00Z&se=2024-01-01T00:00:00Z&sv=2022-11-02&sr=c&sig=example'];
        yield 'Connection string' => ['DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=mykey;EndpointSuffix=core.windows.net'];
        yield 'Simple token' => ['abc123def456'];
        yield 'Token with special chars' => ['token+with/special=chars&more'];
        yield 'Long token' => [str_repeat('a', 500)];
        yield 'Token with spaces (trimmed)' => ['  valid_token_with_spaces  '];
    }
}
