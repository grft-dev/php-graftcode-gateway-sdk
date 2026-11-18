<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;

final class CannotLoadComposerJsonException extends Exception
{
    public function __construct()
    {
        return parent::__construct('Cannot load or parse composer.json file');
    }
}
