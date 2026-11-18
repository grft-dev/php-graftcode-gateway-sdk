<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\Enum;

enum PropertiesName: string
{
    case APP_SERVICE_NAME = 'AppServiceName';
    case SERVICE_NAME = 'ServiceName';
    case CLASS_NAME = 'ClassName';
    case METHOD_NAME = 'MethodName';
    case TRACE_ID = 'TraceId';
    /**
     * @deprecated Legacy alias for TRACE_ID emitted alongside it during the
     *             migration window. Dashboards MUST be updated to consume
     *             TraceId; this value will be removed in the next major.
     */
    case OPERATION_ID = 'OperationId';
    case FILE = 'File';
    case LINE = 'Line';
}
