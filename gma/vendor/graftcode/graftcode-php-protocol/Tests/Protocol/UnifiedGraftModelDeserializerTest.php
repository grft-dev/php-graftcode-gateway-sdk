<?php

declare(strict_types=1);

namespace Tests\Protocol;

use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use ReflectionClass;
use ReflectionException;
use GraftcodeProtocol\Protocol\{
    Serializers\TypeSerializer,
    Deserializers\UnifiedGraftModelDeserializer
};
use GraftcodeProtocol\Enums\{
    GraftCodePrimitiveType,
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\UnifiedGraftModelInterface;

final class UnifiedGraftModelDeserializerTest extends TestCase
{
    private function createTestBuffer(array $payloadBytes): array
    {
        $header = array_fill(0, 11, 0);
        $header[0] = RuntimeName::PHP->value;
        $header[10] = UnifiedGraftModelType::LIBRARY->value;

        return array_merge($header, $payloadBytes);
    }

    #[Group('unit')]
    public function testDeserializeString(): void
    {
        $stringValue = 'test';
        $payloadBytes = TypeSerializer::serializeString($stringValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($stringValue, $resultCommand->getPayload()[0]);

        $this->assertSame(RuntimeName::PHP, $resultCommand->getRuntimeName());
        $this->assertSame(UnifiedGraftModelType::LIBRARY, $resultCommand->getUnifiedGraftModelType());
    }

    #[Group('unit')]
    public function testDeserializeInt(): void
    {
        $intValue = 12345;
        $payloadBytes = TypeSerializer::serializeInt($intValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($intValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeBoolTrue(): void
    {
        $payloadBytes = TypeSerializer::serializeBool(true);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame(true, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeBoolFalse(): void
    {
        $payloadBytes = TypeSerializer::serializeBool(false);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame(false, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeFloat(): void
    {
        $floatValue = 1.234;
        $payloadBytes = TypeSerializer::serializeFloat($floatValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());

        $this->assertEqualsWithDelta($floatValue, $resultCommand->getPayload()[0], 0.0001);
    }

    #[Group('unit')]
    public function testDeserializeByte(): void
    {
        $byteValue = 0xAB;
        $payloadBytes = TypeSerializer::serializeByte($byteValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());

        $this->assertSame($byteValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeChar(): void
    {
        $charValue = 'Z';
        $payloadBytes = TypeSerializer::serializeChar($charValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($charValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeLong(): void
    {
        $longValue = 1234567890123456789;
        $payloadBytes = TypeSerializer::serializeLong($longValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($longValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeDouble(): void
    {
        $doubleValue = 1.2345678901234567;
        $payloadBytes = TypeSerializer::serializeDouble($doubleValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertEqualsWithDelta($doubleValue, $resultCommand->getPayload()[0], 0.0000000000000001);
    }

    #[Group('unit')]
    public function testDeserializeUnsignedLong(): void
    {
        $unsignedLongValue = 9223372036854775807; // Max signed 64-bit int

        $payloadBytes = TypeSerializer::serializeLong($unsignedLongValue);

        $payloadBytes[0] = GraftCodePrimitiveType::UNSIGNED_LONG_LONG->value;
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($unsignedLongValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeUnsignedInteger(): void
    {
        $unsignedIntValue = 2147483647; // Max signed 32-bit int

        $payloadBytes = TypeSerializer::serializeInt($unsignedIntValue);

        $payloadBytes[0] = GraftCodePrimitiveType::UNSIGNED_INTEGER->value;
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame($unsignedIntValue, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeNull(): void
    {
        $payloadBytes = TypeSerializer::serializeNull();

        $payloadBytes[0] = GraftCodePrimitiveType::NULL->value;
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $this->assertCount(1, $resultCommand->getPayload());
        $this->assertSame(null, $resultCommand->getPayload()[0]);
    }

    #[Group('unit')]
    public function testDeserializeMultipleArgs(): void
    {
        $intValue = 99;
        $stringValue = 'hello';

        $payloadBytes = array_merge(
            TypeSerializer::serializeInt($intValue),
            TypeSerializer::serializeString($stringValue),
            TypeSerializer::serializeBool(true)
        );
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $payload = $resultCommand->getPayload();
        $this->assertCount(3, $payload);
        $this->assertSame($intValue, $payload[0]);
        $this->assertSame($stringValue, $payload[1]);
        $this->assertSame(true, $payload[2]);
    }

    #[Group('unit')]
    public function testDeserializeNestedCommand(): void
    {
        $nestedString = 'nested';
        $nestedPayloadBytes = TypeSerializer::serializeString($nestedString);

        $nestedCommandHeader = [
            GraftCodePrimitiveType::GRAFT_MODEL->value,
            1, 0, 0, 0,
            RuntimeName::PHP->value,
            UnifiedGraftModelType::STATIC_METHOD->value
        ];

        $nestedCommandBytes = array_merge($nestedCommandHeader, $nestedPayloadBytes);

        $outerIntValue = 123;
        $outerPayloadBytes = array_merge(
             TypeSerializer::serializeInt($outerIntValue),
             $nestedCommandBytes
        );

        $buffer = $this->createTestBuffer($outerPayloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $resultCommand = $deserializer->deserialize();

        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $resultCommand);
        $payload = $resultCommand->getPayload();
        $this->assertCount(2, $payload);
        $this->assertSame($outerIntValue, $payload[0]);
        $this->assertInstanceOf(UnifiedGraftModelInterface::class, $payload[1]);

        /** @var UnifiedGraftModelInterface $nestedCommand */
        $nestedCommand = $payload[1];
        $this->assertSame(RuntimeName::PHP, $nestedCommand->getRuntimeName());
        $this->assertSame(UnifiedGraftModelType::STATIC_METHOD, $nestedCommand->getUnifiedGraftModelType());
        $nestedPayload = $nestedCommand->getPayload();
        $this->assertCount(1, $nestedPayload);
        $this->assertSame($nestedString, $nestedPayload[0]);
    }

    /**
     * @throws ReflectionException
     */
    #[Group('unit')]
    public function testDeserializeConsumesFullBuffer(): void
    {
        $intValue = 99;
        $payloadBytes = TypeSerializer::serializeInt($intValue);
        $buffer = $this->createTestBuffer($payloadBytes);

        $deserializer = new UnifiedGraftModelDeserializer($buffer);
        $deserializer->deserialize();

        $reflection = new ReflectionClass(UnifiedGraftModelDeserializer::class);
        $isNotEndMethod = $reflection->getMethod('isNotEnd');

        $this->assertFalse($isNotEndMethod->invoke($deserializer), 'Deserializer should be at the end after processing the buffer.');
    }
}
