<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Enum;

enum HttpMethodName: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
}
