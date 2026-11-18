<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use ReflectionMethod;

final class InvokeAbstractStaticMethodAnalyzerHandler extends InvokeStaticMethodAnalyzerHandler
{
    protected function getMethodType(ReflectionMethod $analyzedMethod): UnifiedGraftModelType
    {
        return UnifiedGraftModelType::ABSTRACT_STATIC_METHOD;
    }
}
