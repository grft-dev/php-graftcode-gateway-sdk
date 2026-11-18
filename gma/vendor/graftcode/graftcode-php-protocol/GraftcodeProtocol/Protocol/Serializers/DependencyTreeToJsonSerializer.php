<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Serializers;

use GraftcodeProtocol\Models\DependencyTreeModel;

final readonly class DependencyTreeToJsonSerializer
{
    private function __construct()
    {
    }

    public static function serialize(DependencyTreeModel $model): string
    {
        return json_encode($model, JSON_THROW_ON_ERROR);
    }
}
