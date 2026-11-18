<?php

declare(strict_types=1);

namespace Tests\Protocol;

use GraftcodeProtocol\Const\StringEncodingName;
use GraftcodeProtocol\Protocol\Deserializers\TypeDeserializer;
use GraftcodeProtocol\Enums\StringEncodingMode;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use GraftcodeProtocol\Utils\Exception\ConversionByteToStringException;
use Tests\DataSeed\ProtocolDataSeed;

final class TypeDeserializerTest extends TestCase
{
    #[Group('unit')]
    public function test_DeserializeString_UTF8(): void
    {
        $this->assertSame(ProtocolDataSeed::ASCII_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF8,
                array_slice(ProtocolDataSeed::ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF8_nonAscii(): void
    {
        $this->assertSame(ProtocolDataSeed::NO_ASCII_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF8,
                array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF8_Empty(): void
    {
        $this->assertSame(ProtocolDataSeed::EMPTY_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF8,
                array_slice(ProtocolDataSeed::EMPTY_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_ASCII(): void
    {
        $this->assertSame(ProtocolDataSeed::EMPTY_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::ASCII,
                array_slice(ProtocolDataSeed::EMPTY_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_ASCII_nonAscii(): void
    {
        $this->assertSame(ProtocolDataSeed::NO_ASCII_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF8,
                array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_ASCII_Exception(): void
    {
        $this->expectException(ConversionByteToStringException::class);
        $this->expectExceptionMessage(sprintf('Failed to convert string from: [%s] to: %s', implode(', ', array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)), StringEncodingName::ASCII));


        $this->assertSame(ProtocolDataSeed::NO_ASCII_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::ASCII,
                array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_ASCII_Empty(): void
    {
        $this->assertSame(ProtocolDataSeed::EMPTY_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::ASCII,
                array_slice(ProtocolDataSeed::EMPTY_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF16(): void
    {
        $this->assertSame(ProtocolDataSeed::UTF16LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF16,
                array_slice(ProtocolDataSeed::ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF16_nonAscii(): void
    {
        $this->assertSame(ProtocolDataSeed::NO_ASCII_UTF16LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF16,
                array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF16_Empty(): void
    {
        $this->assertSame(ProtocolDataSeed::EMPTY_UTF16LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF16,
                array_slice(ProtocolDataSeed::EMPTY_STRING_SERIALIZED, 5)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF32(): void
    {
        $this->assertSame(ProtocolDataSeed::UTF32LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF32,
                array_slice(ProtocolDataSeed::ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF32_Empty(): void
    {
        $this->assertSame(ProtocolDataSeed::EMPTY_UTF32LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF32,
                array_slice(ProtocolDataSeed::EMPTY_STRING_SERIALIZED, 5)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeString_UTF32_nonAscii(): void
    {
        $this->assertSame(ProtocolDataSeed::NO_ASCII_UTF32LE_STRING_DESERIALIZED,
            TypeDeserializer::deserializeString(
                StringEncodingMode::UTF32,
                array_slice(ProtocolDataSeed::NO_ASCII_STRING_SERIALIZED, 6)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeInt(): void
    {
        $this->assertSame(
            ProtocolDataSeed::INT_DESERIALIZED,
            TypeDeserializer::deserializeInt(
                array_slice(ProtocolDataSeed::INT_SERIALIZED, 2)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeBool_true(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BOOL_DESERIALIZED_TRUE,
            TypeDeserializer::deserializeBool(
                ProtocolDataSeed::BOOL_SERIALIZED_TRUE[2]
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeBool_false(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BOOL_DESERIALIZED_FALSE,
            TypeDeserializer::deserializeBool(0)
        );
    }

    #[Group('unit')]
    public function test_DeserializeFloat(): void
    {
        {
            $this->assertEquals(
                ProtocolDataSeed::FLOAT_DESERIALIZED,
                TypeDeserializer::deserializeFloat(
                    array_slice(ProtocolDataSeed::FLOAT_SERIALIZED, 2)
                ),
            );
        }
    }

    #[Group('unit')]
    public function test_DeserializeByte(): void
    {
        $this->assertSame(
            ProtocolDataSeed::BYTE_DESERIALIZED,
            TypeDeserializer::deserializeByte(
                array_slice(ProtocolDataSeed::BYTE_SERIALIZED, 2)[0]
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeChar(): void
    {
        $this->assertSame(
            ProtocolDataSeed::CHAR_DESERIALIZED,
            TypeDeserializer::deserializeChar(
                array_slice(ProtocolDataSeed::CHAR_SERIALIZED, 2)[0]
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeLong(): void
    {
        $this->assertSame(
            ProtocolDataSeed::LONG_DESERIALIZED,
            TypeDeserializer::deserializeLong(
                array_slice(ProtocolDataSeed::LONG_SERIALIZED, 2)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeDouble(): void
    {
        $this->assertSame(
            ProtocolDataSeed::DOUBLE_DESERIALIZED,
            TypeDeserializer::deserializeDouble(
                array_slice(ProtocolDataSeed::DOUBLE_SERIALIZED, 2)
            )
        );
    }

    #[Group('unit')]
    public function test_DeserializeNull(): void
    {
        $this->assertNull(TypeDeserializer::deserializeNull());
    }
}
