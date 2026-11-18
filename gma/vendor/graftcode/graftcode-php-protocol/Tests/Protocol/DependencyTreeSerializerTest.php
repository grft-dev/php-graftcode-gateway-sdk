<?php

declare(strict_types=1);

namespace Tests\Protocol;

use GraftcodeProtocol\Models\DependencyTreeModel;
use GraftcodeProtocol\Protocol\Deserializers\DependencyTreeFromJsonDeserializer;
use GraftcodeProtocol\Protocol\Serializers\DependencyTreeToJsonSerializer;
use PHPUnit\Framework\TestCase;

final class DependencyTreeSerializerTest extends TestCase
{
    public function testRoundTripPreservesTree(): void
    {
        $root = new DependencyTreeModel(dependencyName: 'MyPackage', dependencyVersion: '1.0.0');
        $root->dependencyChildren[] = new DependencyTreeModel(dependencyName: 'Child', dependencyVersion: '2.0.0');

        $json = DependencyTreeToJsonSerializer::serialize($root);
        $deserialized = DependencyTreeFromJsonDeserializer::deserialize($json);

        self::assertSame('MyPackage', $deserialized->dependencyName);
        self::assertCount(1, $deserialized->dependencyChildren);
        self::assertSame('Child', $deserialized->dependencyChildren[0]->dependencyName);
        self::assertStringContainsString('"DependencyName"', $json);
    }

    public function testDeserializeEmptyObjectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DependencyTreeFromJsonDeserializer::deserialize('{}');
    }
}
