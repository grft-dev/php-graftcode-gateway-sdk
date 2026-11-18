<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum ParsableTypesEnum
{
    case PARSED_DATETIME;
    case PARSED_DATETIME_OFFSET;
    case PARSED_TIME;
    case PARSED_DURATION;
    case PARSED_UUID;
    case PARSED_URI;
    case PARSED_POINT;
    case PARSED_COLOR;
}
