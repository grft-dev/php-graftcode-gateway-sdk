<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum InheritanceKind: int
{
    case BASE_CLASS = 0;
    case INTERFACE = 1;
    case UNKNOWN = 2;
}

