<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Serializers;

use GraftcodeProtocol\Models\UnifiedGraftModelInterface;
use GraftcodeProtocol\Protocol\Deserializers\UnifiedGraftModelDeserializer;

final readonly class UnifiedGraftModelToJsonSerializer
{
    private function __construct()
    {}

    private function __clone()
    {}

    public function __wakeup()
    {}

    public function __sleep()
    {}

    public static function serialize(UnifiedGraftModelInterface $model): string
    {
        return json_encode(self::getPayload($model), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function getPayload(UnifiedGraftModelInterface $model): array
    {
        $payloadData = [];
        foreach ($model->getPayload() as $payloadItem) {
            if ($payloadItem instanceof UnifiedGraftModelInterface) {
                $payloadData[] = self::getpayload($payloadItem);
            } else {
                $payloadData[] = $payloadItem;
            }
        }

        return [
            'runtimeName' => $model->getRuntimeName()->name,
            'unifiedGraftModelType' => $model->getUnifiedGraftModelType()->name,
            'payload' => $payloadData,
        ];
    }

    public static function serializeFromByteArray(array $byteArray): string
    {
        $deserializer = new UnifiedGraftModelDeserializer($byteArray);

        return self::serialize($deserializer->deserialize());
    }
}
