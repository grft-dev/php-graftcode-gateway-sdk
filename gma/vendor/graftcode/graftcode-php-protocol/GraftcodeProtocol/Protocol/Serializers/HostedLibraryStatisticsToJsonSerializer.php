<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol\Serializers;

use GraftcodeProtocol\Models\HostedLibraryStatisticsModel;

final readonly class HostedLibraryStatisticsToJsonSerializer
{
    public static function serialize(HostedLibraryStatisticsModel $model): string
    {
        return json_encode($model, JSON_THROW_ON_ERROR);
    }
}
