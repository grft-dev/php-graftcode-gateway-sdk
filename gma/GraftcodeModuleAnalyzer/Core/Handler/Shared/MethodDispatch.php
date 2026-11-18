<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use ReflectionMethod;

final readonly class MethodDispatch
{
    public static function resolveNodeType(ReflectionMethod $method): UnifiedGraftModelType
    {
        return match ([$method->isStatic(), $method->isAbstract()]) {
            [true, true] => UnifiedGraftModelType::ABSTRACT_STATIC_METHOD,
            [true, false] => UnifiedGraftModelType::STATIC_METHOD,
            [false, true] => UnifiedGraftModelType::ABSTRACT_INSTANCE_METHOD,
            default => UnifiedGraftModelType::INSTANCE_METHOD,
        };
    }
}
