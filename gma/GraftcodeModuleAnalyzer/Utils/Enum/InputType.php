<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Enum;

enum InputType: string
{
    case Zip = 'zip';
    case Directory = 'directory';
}
