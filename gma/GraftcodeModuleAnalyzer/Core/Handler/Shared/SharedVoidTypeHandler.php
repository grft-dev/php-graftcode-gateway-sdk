<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};

/**
 * Handler for void types.
 * Matches netcore's SharedVoidTypeHandler implementation.
 */
final class SharedVoidTypeHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        $typeUsageWrapper = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_USAGE
        );

        $voidModel = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_USAGE_VOID
        );

        return $typeUsageWrapper->addArgToPayload($voidModel);
    }
}
