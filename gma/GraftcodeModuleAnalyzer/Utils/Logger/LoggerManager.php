<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Logger;

use Exception;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\LoggerClientInterface;
use GraftcodeModuleAnalyzer\Utils\Exception\UnserializeSingletonException;
use Throwable;

final class LoggerManager
{
    private static ?LoggerClientInterface $client = null;

    private function __construct()
    {}

    private function __clone()
    {}

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new UnserializeSingletonException();
    }

    /**
     * @throws Throwable
     */
    public static function getClient(): LoggerClientInterface
    {
        try {
            if (null === self::$client) {
                self::$client = LoggerFactory::initialize();
            }

            return self::$client;
        } catch (Throwable $e) {
            self::reset();

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public static function getLogger(): LoggerInterface
    {
        return self::getClient()->getLogger();
    }

    public static function reset(): void
    {
        self::$client = null;
    }
}
