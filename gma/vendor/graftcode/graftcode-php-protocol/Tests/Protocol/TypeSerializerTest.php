<?php

declare(strict_types=1);

namespace Tests\Protocol;

use GraftcodeProtocol\Protocol\Serializers\TypeSerializer;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use stdClass;
use GraftcodeProtocol\Enums\{
    UnifiedGraftModelType,
    RuntimeName
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use GraftcodeProtocol\Utils\Exception\TypeSerializationNotSupportedException;
use Tests\DataSeed\ProtocolDataSeed;

final class TypeSerializerTest extends TestCase
{
    #[Group('unit')]
    public function test_SerializeNull(): void
    {
        $this->assertSame(ProtocolDataSeed::NULL_SERIALIZED, TypeSerializer::serializeNull());
    }

    #[Group('unit')]
    public function test_SerializeChar(): void
    {
        $this->assertSame(
            ProtocolDataSeed::CHAR_SERIALIZED,
            TypeSerializer::serializeChar(ProtocolDataSeed::CHAR_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeString(): void
    {
        $this->assertSame(
            ProtocolDataSeed::ASCII_STRING_SERIALIZED,
            TypeSerializer::serializeString(ProtocolDataSeed::ASCII_STRING_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeString_nonAscii(): void
    {
        $this->assertSame(
            ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED,
            TypeSerializer::serializeString(ProtocolDataSeed::NO_ASCII_STRING_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeString_Empty(): void
    {
        $this->assertSame(
            ProtocolDataSeed::EMPTY_STRING_SERIALIZED,
            TypeSerializer::serializeString(ProtocolDataSeed::EMPTY_STRING_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeInt(): void
    {
        $this->assertSame(
            ProtocolDataSeed::INT_SERIALIZED,
            TypeSerializer::serializeInt(ProtocolDataSeed::INT_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeInt_max(): void
    {
        $this->assertSame(
            ProtocolDataSeed::INT_MAX_SERIALIZED,
            TypeSerializer::serializeInt(ProtocolDataSeed::INT_MAX_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeInt_min(): void
    {
        $this->assertSame(
            ProtocolDataSeed::INT_MIN_SERIALIZED,
            TypeSerializer::serializeInt(ProtocolDataSeed::INT_MIN_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeBool_true(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BOOL_SERIALIZED_TRUE,
            TypeSerializer::serializeBool(ProtocolDataSeed::BOOL_DESERIALIZED_TRUE)
        );
    }

    public function test_SerializeBool_false(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BOOL_SERIALIZED_FALSE,
            TypeSerializer::serializeBool(ProtocolDataSeed::BOOL_DESERIALIZED_FALSE)
        );
    }

    #[Group('unit')]
    public function test_SerializeFloat(): void
    {
        $this->assertSame(
            ProtocolDataSeed::FLOAT_SERIALIZED,
            TypeSerializer::serializeFloat(ProtocolDataSeed::FLOAT_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeFloat_max(): void
    {
        $this->assertSame(
            ProtocolDataSeed::FLOAT_MAX_SERIALIZED,
            TypeSerializer::serializeFloat(ProtocolDataSeed::FLOAT_MAX_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeFloat_min(): void
    {
        $this->assertSame(
            ProtocolDataSeed::FLOAT_MIN_SERIALIZED,
            TypeSerializer::serializeFloat(ProtocolDataSeed::FLOAT_MIN_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeByte(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BYTE_SERIALIZED,
            TypeSerializer::serializeByte(ProtocolDataSeed::BYTE_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeLong(): void
    {
        $this->assertSame(
            ProtocolDataSeed::LONG_SERIALIZED,
            TypeSerializer::serializeLong(ProtocolDataSeed::LONG_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeLong_min(): void
    {
        $this->assertSame(
            ProtocolDataSeed::LONG_MIN_SERIALIZED,
            TypeSerializer::serializeLong(ProtocolDataSeed::LONG_MIN_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeLong_max(): void
    {
        $this->assertSame(
            ProtocolDataSeed::LONG_MAX_SERIALIZED,
            TypeSerializer::serializeLong(ProtocolDataSeed::LONG_MAX_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_SerializeDouble(): void
    {
        $this->assertSame(
            ProtocolDataSeed::DOUBLE_SERIALIZED,
            TypeSerializer::serializeDouble(ProtocolDataSeed::DOUBLE_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_serializePrimitive_SerializeLong_max(): void
    {
        $this->assertSame(
            ProtocolDataSeed::LONG_MAX_SERIALIZED,
            TypeSerializer::serializeLong(ProtocolDataSeed::LONG_MAX_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_serializePrimitive_SerializeDouble(): void
    {
        $this->assertSame(
            ProtocolDataSeed::DOUBLE_SERIALIZED,
            TypeSerializer::serializePrimitive(ProtocolDataSeed::DOUBLE_DESERIALIZED)
        );
    }

    #[Group('unit')]
    public function test_serializePrimitive_SerializeNull(): void
    {
        $this->assertSame(
            ProtocolDataSeed::NULL_SERIALIZED,
            TypeSerializer::serializePrimitive(null)
        );
    }

    #[Group('unit')]
    public function test_serializePrimitive_TypeSerializationNotSupportedException(): void
    {
        $this->expectException(TypeSerializationNotSupportedException::class);
        $this->expectExceptionMessage('PHP: Type serialization not supported for type: stdClass');

        TypeSerializer::serializePrimitive(new stdClass());
    }

    #[Group('unit')]
    public function test_serializeCommand_emptyPayload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_EMPTY_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(
                new UnifiedGraftModel(
                    RuntimeName::RUBY,
                    UnifiedGraftModelType::ENUM,
                    'enumName'
                )
            ),
        );
    }

    #[Group('unit')]
    public function test_serializeCommand_with_1D_arrayPayload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_WITH_1D_STRING_ARRAY_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(
                new UnifiedGraftModel(
                    RuntimeName::PHP,
                    UnifiedGraftModelType::GETTER,
                    'getterName'
                ),
            ),
        );
    }

    #[Group('unit')]
    public function test_serializeCommand_with_string_and_numbers_Payload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_WITH_STING_AND_NUMBERS_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(
                new UnifiedGraftModel(
                    RuntimeName::NONE,
                    UnifiedGraftModelType::MODULE,
                    "three_numbers_adder",
                    5,
                    10,
                    15
                ),
            ),
        );
    }

    #[Group('unit')]
    public function test_serializeCommand_withStringPayload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_WITH_STRING_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(
                new UnifiedGraftModel(
                    RuntimeName::PYTHON,
                    UnifiedGraftModelType::CLASS_NAME,
                    'ClassForStaticTest'
                ),
            ),
        );
    }

    #[Group('unit')]
    public function test_serializeCommand_withMixedPayload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_WITH_MIXED_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(
                new UnifiedGraftModel(
                    RuntimeName::JVM,
                    UnifiedGraftModelType::ARGUMENT_NAME,
                    new UnifiedGraftModel(
                        RuntimeName::RUBY,
                        UnifiedGraftModelType::ARRAY,
                        ['123', 'test_2']
                    ),
                ),
            ),
        );
    }

    #[Group('unit')]
    public function test_serializeCommand_withMultiMixedPayload(): void
    {
        $this->assertSame(
            ProtocolDataSeed::COMMAND_WITH_MULTI_MIXED_PAYLOAD_SERIALIZED,
            TypeSerializer::serializeCommand(ProtocolDataSeed::getNestedRubyCommand()),
        );
    }
}
