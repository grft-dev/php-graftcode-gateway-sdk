<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum GraftCodePrimitiveType: int
{
    case GRAFT_MODEL = 0;
    case STRING = 1;
    case INTEGER = 2;
    case BOOLEAN = 3;
    case FLOAT = 4;
    case BYTE = 5;
    case CHAR = 6;
    case LONG = 7;
    case DOUBLE = 8;
    case UNSIGNED_LONG_LONG = 9;
    case UNSIGNED_INTEGER = 10;
    case NULL = 11;
    case UNDEFINED = 12;
    case DELEGATE = 13;
    case UNKNOWN = 14;
}

