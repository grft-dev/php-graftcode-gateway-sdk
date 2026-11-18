<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Utils\MethodCompletionAnalyzerHelper;
use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use ReflectionFunction;
use ReflectionMethod;

final class SharedMethodCompletionAnalyzerHandler
{
    public static function appendToPayloadIfNeeded(
        ReflectionMethod|ReflectionFunction $method,
        UnifiedGraftModel $methodCommand
    ): UnifiedGraftModel {
        $facts = MethodCompletionAnalyzerHelper::analyze($method);
        if (!$facts['isAsyncCall'] && !$facts['hasDeferredReturn']) {
            return $methodCommand;
        }

        $completion = new UnifiedGraftModel(RuntimeName::PHP, UnifiedGraftModelType::METHOD_COMPLETION);
        $completion = $completion->addArgToPayload($facts['isAsyncCall']);
        $completion = $completion->addArgToPayload($facts['hasDeferredReturn']);

        return $methodCommand->addArgToPayload($completion);
    }
}
