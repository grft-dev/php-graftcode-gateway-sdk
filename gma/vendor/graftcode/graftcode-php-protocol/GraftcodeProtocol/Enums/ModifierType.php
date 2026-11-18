<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum ModifierType: int
{
    case PUBLIC = 0;
    case PRIVATE = 1;
    case PROTECTED = 2;
    case PACKAGE_PRIVATE = 3;

    public function getLowerName(): string
    {
        return strtolower($this->name);
    }
}
