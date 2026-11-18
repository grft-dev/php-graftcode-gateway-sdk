<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Logger;

use Exception;
use GraftCodeLogger\{
    LoggerClient,
    LoggerClientInterface,
    Utils\VO\LoggerClientOptions
};
use GraftcodeModuleAnalyzer\Utils\Exception\{
    FailedInitializeLoggerClientException,
    UnserializeSingletonException
};

final readonly class LoggerFactory
{
    private function __construct()
    {}

    private function __clone()
    {}

    /**
     * @throws UnserializeSingletonException
     */
    public function __wakeup()
    {
        throw new UnserializeSingletonException();
    }

    /**
     * @throws FailedInitializeLoggerClientException
     */
    public static function initialize(): LoggerClientInterface
    {
        try {
            $loggerConfig = new LoggerClientOptions(
                getenv('WEBSITE_SITE_NAME') ?: 'g-d-app-polc-gma-php-01',
                getenv('SERVICE_NAME') ?: 'GraftCodeAnalyzerPhp'
            );

            return new LoggerClient($loggerConfig);
        } catch (Exception $e) {
            throw new FailedInitializeLoggerClientException($e);
        }
    }
}
