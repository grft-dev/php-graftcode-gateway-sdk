<?php

declare(strict_types=1);

namespace Protocol;

use GraftcodeProtocol\Protocol\Serializers\UnifiedGraftModelSerializer;
use GraftcodeProtocol\Enums\{
    GraftCodePrimitiveType,
    RuntimeName,
    StringEncodingMode,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};

final class UnifiedGraftModelSerializerTest extends TestCase
{
    #[Group('unit')]
    public function test_shouldSerializeSimpleModelWithPrimitives(): void
    {
        $rootCommand = new UnifiedGraftModel(
            RuntimeName::GO,
            UnifiedGraftModelType::STATIC_METHOD,
            123,
            "abc"
        );

        $expectedOutput = [
            RuntimeName::GO->value,
            0,
            0, 0, 0, 0, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::STATIC_METHOD->value,
            GraftCodePrimitiveType::INTEGER->value, 4, 123, 0, 0, 0,
            GraftCodePrimitiveType::STRING->value, StringEncodingMode::UTF8->value, 3, 0, 0, 0, 97, 98, 99,
        ];

        $serialized = UnifiedGraftModelSerializer::serialize($rootCommand);

        $this->assertSame($expectedOutput, $serialized);
    }

    #[Group('unit')]
    public function test_shouldSerializeModelWithEmptyPayload(): void
    {
        $rootCommand = new UnifiedGraftModel(
            RuntimeName::NODEJS,
            UnifiedGraftModelType::LIBRARY
        );

        $expectedOutput = [
            RuntimeName::NODEJS->value,
            0,
            0, 0, 0, 0, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::LIBRARY->value,
        ];

        $serialized = UnifiedGraftModelSerializer::serialize($rootCommand);

        $this->assertSame($expectedOutput, $serialized);
    }

    #[Group('unit')]
    public function test_shouldSerializeModelWithNestedCommand(): void
    {
        $nestedCommand = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_DEF,
            true,
            null
        );

        $rootCommand = new UnifiedGraftModel(
            RuntimeName::JVM,
            UnifiedGraftModelType::CONSTRUCTOR,
            45.67,
            $nestedCommand
        );

        $serializedNestedPayload = [
            GraftCodePrimitiveType::BOOLEAN->value, 1, 1,
            GraftCodePrimitiveType::NULL->value, 1, 0,
        ];

        $expectedOutput = [
            RuntimeName::JVM->value,
            0,
            0, 0, 0, 0, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::CONSTRUCTOR->value,
            GraftCodePrimitiveType::FLOAT->value, 4, ...unpack('C*', pack('f', 45.67)),
            GraftCodePrimitiveType::GRAFT_MODEL->value,
            $nestedCommand->getPayloadSize(), 0, 0, 0,
            $nestedCommand->getRuntimeName()->value,
            $nestedCommand->getUnifiedGraftModelType()->value,
            ...$serializedNestedPayload
        ];

        $serialized = UnifiedGraftModelSerializer::serialize($rootCommand);

        $this->assertSame($expectedOutput, $serialized);
    }

    #[Group('unit')]
    public function test_shouldSerializeModelWithVariousPrimitives(): void
    {
        $rootCommand = new UnifiedGraftModel(
            RuntimeName::PYTHON,
            UnifiedGraftModelType::INSTANCE_METHOD,
            10,
            "test",
            true,
            false,
            null,
            123.45,
            PHP_INT_MAX
        );

        $expectedOutput = [
            RuntimeName::PYTHON->value,
            0,
            0, 0, 0, 0, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::INSTANCE_METHOD->value,
            GraftCodePrimitiveType::INTEGER->value, 4, 10, 0, 0, 0,
            GraftCodePrimitiveType::STRING->value, StringEncodingMode::UTF8->value, 4, 0, 0, 0, 116, 101, 115, 116,
            GraftCodePrimitiveType::BOOLEAN->value, 1, 1,
            GraftCodePrimitiveType::BOOLEAN->value, 1, 0,
            GraftCodePrimitiveType::NULL->value, 1, 0,
            GraftCodePrimitiveType::FLOAT->value, 4, ...unpack('C*', pack('f', 123.45)),
            GraftCodePrimitiveType::LONG->value, 8, ...unpack('C*', pack('P', PHP_INT_MAX)),
        ];

        $serialized = UnifiedGraftModelSerializer::serialize($rootCommand);

        $this->assertSame($expectedOutput, $serialized);
    }

    #[Group('unit')]
    public function test_shouldSerializeDeeplyNestedCommands(): void
    {
        $cmd3 = new UnifiedGraftModel(RuntimeName::NODEJS, UnifiedGraftModelType::MODULE, "level3");
        $cmd2 = new UnifiedGraftModel(RuntimeName::PYTHON, UnifiedGraftModelType::ARRAY, 99, $cmd3);
        $cmd1 = new UnifiedGraftModel(RuntimeName::GO, UnifiedGraftModelType::REF, $cmd2);

        $serializedCmd3Payload = [
            GraftCodePrimitiveType::STRING->value, StringEncodingMode::UTF8->value, 6, 0, 0, 0, 108, 101, 118, 101, 108, 51,
        ];
        $serializedCmd2Payload = [
            GraftCodePrimitiveType::INTEGER->value, 4, 99, 0, 0, 0,
            GraftCodePrimitiveType::GRAFT_MODEL->value,
            count($cmd3->getPayload()), 0, 0, 0,
            $cmd3->getRuntimeName()->value,
            $cmd3->getUnifiedGraftModelType()->value,
            ...$serializedCmd3Payload
        ];

        $serializedCmd1Payload = [
            GraftCodePrimitiveType::GRAFT_MODEL->value,
            count($cmd2->getPayload()), 0, 0, 0,
            $cmd2->getRuntimeName()->value,
            $cmd2->getUnifiedGraftModelType()->value,
            ...$serializedCmd2Payload
        ];

        $expectedOutput = [
            RuntimeName::GO->value,
            0,
            0, 0, 0, 0, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::REF->value,
            ...$serializedCmd1Payload
        ];

        $serialized = UnifiedGraftModelSerializer::serialize($cmd1);

        $this->assertSame($expectedOutput, $serialized);
    }
}
