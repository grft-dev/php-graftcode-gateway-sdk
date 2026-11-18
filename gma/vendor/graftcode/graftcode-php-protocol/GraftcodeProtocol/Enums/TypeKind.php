<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum TypeKind: int
{
    case CLASS_TYPE = 0;
    case INTERFACE = 1;
    case ABSTRACT_CLASS = 2;
}
