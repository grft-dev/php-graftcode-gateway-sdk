<?php

declare(strict_types=1);

namespace GraftCodeLogger\Logger\Factory;

use GraftCodeLogger\Logger\Logger;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\Utils\VO\LoggerClientOptions;
use GraftCodeLogger\Utils\VO\TraceScope;

final readonly class LoggerFactory implements LoggerFactoryInterface
{
    public static function createLogger(
        LoggerClientOptions $config,
        ?TraceScope $scope = null,
    ): LoggerInterface {
        return new Logger($config, $scope);
    }
}
