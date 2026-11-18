<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;

final class MissingComposerVersionException extends Exception
{
    public function __construct()
    {
        return parent::__construct('The composer.json file does not contain a version field');
    }
}
