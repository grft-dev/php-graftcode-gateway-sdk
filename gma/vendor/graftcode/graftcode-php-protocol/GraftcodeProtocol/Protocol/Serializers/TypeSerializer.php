<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Serializers;

use GraftcodeProtocol\Protocol\StringEncodingHelper;
use GraftcodeProtocol\Utils\{
    UtilsSimpleType,
    Exception\TypeSerializationNotSupportedException
};
use GraftcodeProtocol\Enums\{
    StringEncodingMode,
    GraftCodePrimitiveType,
    GraftCodePrimitiveTypeSize
};
use GraftcodeProtocol\Models\UnifiedGraftModelInterface;

final readonly class TypeSerializer
{
    private function __construct()
    {}

    public static function serializePrimitive(mixed $value): array
    {
        if (is_null($value)) {
            return self::serializeNull();
        }

        if (UtilsSimpleType::isChar($value)) {
            return self::serializeChar($value);
        }

        if (is_string($value)) {
            return self::serializeString($value);
        }

        if (UtilsSimpleType::isFloat($value)) {
            return self::serializeFloat($value);
        }

        if (is_float($value)) {
            return self::serializeDouble($value);
        }

        if (is_bool($value)) {
            return self::serializeBool($value);
        }

        if (UtilsSimpleType::isInteger($value)) {
            return self::serializeInt($value);
        }

        if (UtilsSimpleType::isLong($value)) {
            return self::serializeLong($value);
        }

        throw new TypeSerializationNotSupportedException($value);
    }

    public static function serializeNull(): array
    {
        return [
            GraftCodePrimitiveType::NULL->value,
            GraftCodePrimitiveTypeSize::NULL_SIZE->getValue(),
            0
        ];
    }

    public static function serializeString(string $val): array
    {
        $bytes = StringEncodingHelper::utf8StringToByteArray($val);
        $buffer[] = GraftCodePrimitiveType::STRING->value;
        $buffer[] = StringEncodingMode::UTF8->value;
        array_push($buffer, ...self::serializeIntValue(count($bytes)));

        return array_merge($buffer, $bytes);
    }

    public static function serializeInt(int $intVal): array
    {
        return [
            GraftCodePrimitiveType::INTEGER->value,
            GraftCodePrimitiveTypeSize::INTEGER_SIZE->getValue(),
            $intVal & 0xFF,
            ($intVal >> 8) & 0xFF,
            ($intVal >> 16) & 0xFF,
            ($intVal >> 24) & 0xFF
        ];
    }

    public static function serializeBool(bool $boolValue): array
    {
        return [
            GraftCodePrimitiveType::BOOLEAN->value,
            GraftCodePrimitiveTypeSize::BOOLEAN_SIZE->getValue(),
            $boolValue ? 1 : 0
        ];
    }

    public static function serializeFloat(float $floatValue): array
    {
        $bytesFloat = unpack('l', pack('f', $floatValue))[1];
        return [
            GraftCodePrimitiveType::FLOAT->value,
            GraftCodePrimitiveTypeSize::FLOAT_SIZE->getValue(),
            $bytesFloat & 0xFF,
            ($bytesFloat >> 8) & 0xFF,
            ($bytesFloat >> 16) & 0xFF,
            ($bytesFloat >> 24) & 0xFF
        ];
    }

    public static function serializeByte(int $byteValue): array
    {
        return [
            GraftCodePrimitiveType::BYTE->value,
            GraftCodePrimitiveTypeSize::BYTE_SIZE->getValue(),
            $byteValue & 0xFF
        ];
    }

    public static function serializeChar(string $charValue): array
    {
        return [
            GraftCodePrimitiveType::CHAR->value,
            GraftCodePrimitiveTypeSize::CHAR_SIZE->getValue(),
            ord($charValue)
        ];
    }

    public static function serializeLong(int $longValue): array
    {
        return [
            GraftCodePrimitiveType::LONG->value,
            GraftCodePrimitiveTypeSize::LONG_SIZE->getValue(),
            $longValue & 0xFF,
            ($longValue >> 8) & 0xFF,
            ($longValue >> 16) & 0xFF,
            ($longValue >> 24) & 0xFF,
            ($longValue >> 32) & 0xFF,
            ($longValue >> 40) & 0xFF,
            ($longValue >> 48) & 0xFF,
            ($longValue >> 56) & 0xFF,
        ];
    }

    public static function serializeDouble(float $doubleValue): array
    {
        $value = unpack('P', pack('d', $doubleValue))[1];
        return [
            GraftCodePrimitiveType::DOUBLE->value,
            GraftCodePrimitiveTypeSize::DOUBLE_SIZE->getValue(),
            $value & 0xFF,
            ($value >> 8) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 24) & 0xFF,
            ($value >> 32) & 0xFF,
            ($value >> 40) & 0xFF,
            ($value >> 48) & 0xFF,
            ($value >> 56) & 0xFF,
        ];
    }

    private static function serializeIntValue(int $intValue): array
    {
        return [
            $intValue & 0xFF,
            ($intValue >> 8) & 0xFF,
            ($intValue >> 16) & 0xFF,
            ($intValue >> 24) & 0xFF
        ];
    }

    public static function serializeCommand(UnifiedGraftModelInterface $cmd): array
    {
        $buffer[] = GraftCodePrimitiveType::GRAFT_MODEL->value;
        array_push($buffer, ...self::serializeIntValue($cmd->getPayloadSize()));
        $buffer[] = $cmd->getRuntimeName()->value;
        $buffer[] = $cmd->getUnifiedGraftModelType()->value;

        return $buffer;
    }
}
