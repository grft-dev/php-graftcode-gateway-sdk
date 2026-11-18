<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftCodeLogger\Logger\LoggerInterface;
use GraftcodeModuleAnalyzer\Utils\Logger\LoggerManager;
use Throwable;

class BaseHandler
{
    public LoggerInterface $logger;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->logger = LoggerManager::getLogger();
    }
}
