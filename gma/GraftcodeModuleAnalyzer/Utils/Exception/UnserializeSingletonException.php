<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;

final class UnserializeSingletonException extends Exception
{
    public function __construct()
    {
        return parent::__construct('Cannot unserialize singleton');
    }
}
