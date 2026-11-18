<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum StringEncodingMode: int
{
    case ASCII = 0;
    case UTF8 = 1;
    case UTF16 = 2;
    case UTF32 = 3;
}

