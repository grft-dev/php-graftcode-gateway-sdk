<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Deserializers;

use GraftcodeProtocol\Models\DependencyTreeModel;
use JsonException;

final readonly class DependencyTreeFromJsonDeserializer
{
    private function __construct()
    {
    }

    public static function deserialize(string $json): DependencyTreeModel
    {
        $trimmedJson = trim($json);
        if ($trimmedJson === '' || !str_starts_with($trimmedJson, '{') || $trimmedJson === '{}') {
            throw new \InvalidArgumentException('Invalid JSON data');
        }

        try {
            /** @var mixed $parsed */
            $parsed = json_decode($trimmedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Invalid JSON data', 0, $exception);
        }

        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('Invalid JSON data');
        }

        return DependencyTreeModel::fromArray($parsed);
    }
}
