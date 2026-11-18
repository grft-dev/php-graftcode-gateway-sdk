<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum IsNullable: int
{
    case FALSE = 0;
    case TRUE = 1;
}
