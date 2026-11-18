<?php

declare(strict_types=1);

namespace Tests\Protocol;

use GraftcodeProtocol\Protocol\Serializers\UnifiedGraftModelSerializer;
use GraftcodeProtocol\Enums\{
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use GraftcodeProtocol\Protocol\Serializers\UnifiedGraftModelToJsonSerializer;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};

final class UnifiedGraftModelToJsonSerializerTest extends TestCase
{
    #[Group('unit')]
    public function test_serialize_model_with_empty_payload(): void
    {
        $model = new UnifiedGraftModel(
            RuntimeName::JVM,
            UnifiedGraftModelType::MODULE,
        );
        $expectedJson = '{"runtimeName":"JVM","unifiedGraftModelType":"MODULE","payload":[]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serialize($model);

        $this->assertSame($expectedJson, $actualJson);
    }

    #[Group('unit')]
    public function test_serialize_model_with_multiple_payloads(): void
    {
        $model = new UnifiedGraftModel(
            RuntimeName::PYTHON,
            UnifiedGraftModelType::LIBRARY,
            "TestLibrary",
            'Payload1',
            'Payload2'
        );
        $expectedJson = '{"runtimeName":"PYTHON","unifiedGraftModelType":"LIBRARY","payload":["TestLibrary","Payload1","Payload2"]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serialize($model);

        $this->assertSame($expectedJson, $actualJson);
    }

    #[Group('unit')]
    public function test_serialize_nested_model(): void
    {
        $nestedModel = new UnifiedGraftModel(
            RuntimeName::RUBY,
            UnifiedGraftModelType::TYPE_DEF,
            "TestedTypeDef"
        );
        $model = new UnifiedGraftModel(
            RuntimeName::GO,
            UnifiedGraftModelType::ARGUMENT_NAME,
            'TestArgName',
            $nestedModel
        );
        $expectedJson = '{"runtimeName":"GO","unifiedGraftModelType":"ARGUMENT_NAME","payload":["TestArgName",{"runtimeName":"RUBY","unifiedGraftModelType":"TYPE_DEF","payload":["TestedTypeDef"]}]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serialize($model);

        $this->assertSame($expectedJson, $actualJson);
    }

    #[Group('unit')]
    public function test_serialize_from_byte_array(): void
    {
        $model = new UnifiedGraftModel(
            RuntimeName::NODEJS,
            UnifiedGraftModelType::PROPERTY,
            'TestProperty'
        );

        $byteArray = UnifiedGraftModelSerializer::serialize($model);
        $expectedJson = '{"runtimeName":"NODEJS","unifiedGraftModelType":"PROPERTY","payload":["TestProperty"]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serializeFromByteArray($byteArray);

        $this->assertSame($expectedJson, $actualJson);
    }

    #[Group('unit')]
    public function test_serialize_from_byte_array_with_multiple_payloads(): void
    {
        $model = new UnifiedGraftModel(
            RuntimeName::NETCORE,
            UnifiedGraftModelType::CLAZZ_INSTANCE,
            "TestLibrary",
            "Payload1",
            'Payload2'
        );

        $byteArray = UnifiedGraftModelSerializer::serialize($model);
        $expectedJson = '{"runtimeName":"NETCORE","unifiedGraftModelType":"CLAZZ_INSTANCE","payload":["TestLibrary","Payload1","Payload2"]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serializeFromByteArray($byteArray);

        $this->assertSame($expectedJson, $actualJson);
    }

    #[Group('unit')]
    public function test_serialize_simple_model(): void
    {
        $model = new UnifiedGraftModel(
            RuntimeName::PERL,
            UnifiedGraftModelType::OUT,
            "TestLibrary"
        );
        $expectedJson = '{"runtimeName":"PERL","unifiedGraftModelType":"OUT","payload":["TestLibrary"]}';
        $actualJson = UnifiedGraftModelToJsonSerializer::serialize($model);

        $this->assertSame($expectedJson, $actualJson);
    }
}
