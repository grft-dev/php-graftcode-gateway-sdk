<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\Enum;

enum SeverityLevel: int
{
    case VERBOSE = 0;
    case INFORMATION = 1;
    case WARNING = 2;
    case ERROR = 3;
    case CRITICAL = 4;
}
