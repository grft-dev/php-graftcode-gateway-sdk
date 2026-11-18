<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    AnalyzedObjectUtils,
    Const\Types
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use Throwable;

final class SharedNamespaceHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try
        {
            if (!is_string($analyzedObject)) {
                $this->logger->trackVerbose('Analyzed namespace object is not a string, returning null');

                return Types::NULL;
            }

            $this->logger->trackVerbose(sprintf(
                'Processing namespace: {%s}',
                $analyzedObject
            ));

            return $analyzedObject;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                sprintf(
                'Error processing namespace: {%s}',
                    AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
                )
            ]);

            return Types::UNKNOWN;
        }
    }
}
