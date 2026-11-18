<?php

declare(strict_types=1);

namespace Unit;

use Error;
use GraftcodeStorage\StorageClient;
use GraftcodeStorage\StorageClientInterface;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use ReflectionClass;
use ReflectionMethod;

final class StorageClientTest extends TestCase
{
    #[Group('unit')]
    public function testGetStorageReturnsSameInstance(): void
    {
        $client = new StorageClient();
        $storage1 = $client->getStorage();
        $storage2 = $client->getStorage();
        $this->assertSame($storage1, $storage2, 'getStorage should return the same object');
    }

    #[Group('unit')]
    public function testImplementsStorageClientInterface(): void
    {
        $client = new StorageClient();
        $this->assertInstanceOf(StorageClientInterface::class, $client);
    }

    #[Group('unit')]
    public function testNoPublicSettersForStorage(): void
    {
        $reflection = new ReflectionClass(StorageClient::class);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertStringNotContainsString('set', $method->getName(), 'There should not be public setters.');
        }
    }

    #[Group('unit')]
    public function testNoUnexpectedPublicMethods(): void
    {
        $reflection = new ReflectionClass(StorageClient::class);
        $expected = ['__construct', 'getStorage'];
        $actual = array_map(fn($m) => $m->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));

        foreach ($actual as $method) {
            $this->assertContains($method, $expected, 'Unexpected public method: '. $method);
        }
    }

    #[Group('unit')]
    public function testCannotOverwriteStorageProperty(): void
    {
        $this->expectException(Error::class);

        $client = new StorageClient();
        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('storage');

        $property->setValue($client, null);
    }
}
