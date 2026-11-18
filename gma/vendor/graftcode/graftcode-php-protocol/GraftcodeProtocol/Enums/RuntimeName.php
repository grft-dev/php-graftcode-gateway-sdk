<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum RuntimeName: int
{
    case CLR = 0;
    case GO = 1;
    case JVM = 2;
    case NETCORE = 3;
    case PERL = 4;
    case PYTHON = 5;
    case RUBY = 6;
    case NODEJS = 7;
    case CPP = 8;
    case PHP = 9;
    case NONE = 10;

    public function getLowerName(): string
    {
        return strtolower($this->name);
    }
}

