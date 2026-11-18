<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Deserializers;

use GraftcodeProtocol\Enums\GraftCodePrimitiveType;
use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\StringEncodingMode;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Utils\Exception\TypeByteNotSupportedException;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use GraftcodeProtocol\Models\UnifiedGraftModelInterface;

final class UnifiedGraftModelDeserializer
{
    private int $position;
    private UnifiedGraftModelInterface $command;

    public function __construct(
        private readonly array $buffer
    ) {
        $this->command = new UnifiedGraftModel(
            RuntimeName::from($buffer[0]),
            UnifiedGraftModelType::from($buffer[10])
        );
        $this->position = 11;
    }

    private function isNotEnd(): bool
    {
        return $this->position < count($this->buffer);
    }

    public function deserialize(): UnifiedGraftModelInterface
    {
        while ($this->isNotEnd()) {
            $this->command = $this->command->addArgToPayload($this->readObject($this->buffer[$this->position]));
        }

        return $this->command;
    }

    private function readObject(int $typeNum): UnifiedGraftModelInterface|string|int|bool|float|null
    {
        $typeByte = GraftCodePrimitiveType::tryFrom($typeNum);

        return match ($typeByte) {
            GraftCodePrimitiveType::GRAFT_MODEL => $this->readCommand(),
            GraftCodePrimitiveType::STRING => $this->readString(),
            GraftCodePrimitiveType::INTEGER => $this->readInt(),
            GraftCodePrimitiveType::BOOLEAN => $this->readBool(),
            GraftCodePrimitiveType::FLOAT => $this->readFloat(),
            GraftCodePrimitiveType::BYTE => $this->readByte(),
            GraftCodePrimitiveType::CHAR => $this->readChar(),
            GraftCodePrimitiveType::LONG => $this->readLong(),
            GraftCodePrimitiveType::DOUBLE => $this->readDouble(),
            GraftCodePrimitiveType::UNSIGNED_LONG_LONG => $this->readUnsignedLong(),
            GraftCodePrimitiveType::UNSIGNED_INTEGER => $this->readUnsignedInteger(),
            GraftCodePrimitiveType::NULL => $this->readNull(),
            default => throw new TypeByteNotSupportedException($typeNum),
        };
    }

    protected function readCommand(): UnifiedGraftModelInterface
    {
        $p = $this->position;
        $numberOfElementsInPayload = TypeDeserializer::deserializeInt(array_slice($this->buffer, $p + 1, 4));
        $runtime = $this->buffer[$p + 5];
        $commandType = $this->buffer[$p + 6];

        $this->position += 7;

        $payload = [];
        for ($i = 0; $i < $numberOfElementsInPayload; $i++) {
            $payload[$i] = $this->readObject($this->buffer[$this->position]);
        }

        return new UnifiedGraftModel(
            RuntimeName::from($runtime),
            UnifiedGraftModelType::from($commandType),
            $payload
        );
    }

    private function readString(): string
    {
        $p = $this->position;
        $stringEncodingMode = StringEncodingMode::from($this->buffer[$p + 1]);
        $size = TypeDeserializer::deserializeInt(array_slice($this->buffer, $p + 2, 4));
        $this->position += 6;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeString($stringEncodingMode, array_slice($this->buffer, $p, $size));
    }
    private function readInt(): int
    {
        $size = 4;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeInt(array_slice($this->buffer, $p, $size));
    }

    private function readBool(): bool
    {
        $size = 1;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeBool($this->buffer[$p]);
    }

    private function readFloat(): float
    {
        $size = 4;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeFloat(array_slice($this->buffer, $p, $size));
    }

    private function readByte(): int
    {
        $size = 1;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeByte($this->buffer[$p]);
    }

    private function readChar(): string
    {
        $size = 1;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeChar($this->buffer[$p]);
    }

    private function readLong(): int
    {
        $size = 8;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeLong(array_slice($this->buffer, $p, $size));
    }

    private function readDouble(): float
    {
        $size = 8;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeDouble(array_slice($this->buffer, $p, $size));
    }

    private function readUnsignedLong(): int
    {
        $size = 8;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeLong(array_slice($this->buffer, $p, $size));
    }

    private function readUnsignedInteger(): int
    {
        $size = 4;
        $this->position += 2;
        $p = $this->position;
        $this->position += $size;

        return TypeDeserializer::deserializeInt(array_slice($this->buffer, $p, $size));
    }

    private function readNull(): null
    {
        $size = 1;
        $this->position += 2;
        $this->position += $size;

        return null;
    }
}
