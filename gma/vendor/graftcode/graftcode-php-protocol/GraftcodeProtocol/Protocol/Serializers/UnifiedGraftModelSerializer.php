<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Serializers;

use Exception;
use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Models\UnifiedGraftModelInterface;
use RuntimeException;

final readonly class UnifiedGraftModelSerializer
{
    public static function serialize(UnifiedGraftModelInterface $rootCommand): array
    {
        try {
            $buffer[] = $rootCommand->getRuntimeName()->value;
            $buffer[] = 0;

            $buffer = array_merge($buffer, [0, 0, 0, 0, 0, 0, 0]);;

            $buffer[] = RuntimeName::PHP->value;
            $buffer[] = $rootCommand->getUnifiedGraftModelType()->value;

            self::serializeRecursively($rootCommand, $buffer);

            return $buffer;
        } catch (Exception $e) {
            throw new RuntimeException('Error during serialization ' . $e);
        }
    }

    private static function serializeRecursively(UnifiedGraftModelInterface $command, array &$buffer): void
    {
        foreach ($command->getPayload() as $item) {
            if ($item instanceof UnifiedGraftModelInterface) {
                array_push($buffer, ...TypeSerializer::serializeCommand($item));
                self::serializeRecursively($item,$buffer);
            } else {
                array_push($buffer, ...TypeSerializer::serializePrimitive($item));
            }
        }
    }
}
