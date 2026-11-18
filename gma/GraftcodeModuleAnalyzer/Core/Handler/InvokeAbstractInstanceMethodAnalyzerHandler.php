<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use ReflectionMethod;

final class InvokeAbstractInstanceMethodAnalyzerHandler extends InvokeInstanceMethodAnalyzerHandler
{
    protected function getMethodType(ReflectionMethod $analyzedMethod): UnifiedGraftModelType
    {
        return UnifiedGraftModelType::ABSTRACT_INSTANCE_METHOD;
    }
}
