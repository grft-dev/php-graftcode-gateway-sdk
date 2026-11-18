<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    Const\Types,
    Logger\LoggerUtils
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use Throwable;

final class SharedClassNameHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            if (is_string($analyzedObject)) {
                $this->logger->trackVerbose(sprintf(
                    'Processing class name: {%s}',
                    LoggerUtils::getLogSafeValue($analyzedObject)
                    ), ['processing class name' => $analyzedObject]
                );

                return $analyzedObject;
            }

            if (is_object($analyzedObject)) {
                $className = get_class($analyzedObject);
                $this->logger->trackVerbose(sprintf(
                    'Extracted class name from object: {%s}',
                    LoggerUtils::getLogSafeValue($className)
                    ), ['extracted class name' => $className]
                );

                return $className;
            }

            $this->logger->trackVerbose('Analyzed object is not a string or object, returning null');

            return Types::NULL;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'error' => 'Error processing class name',
                'type' => gettype($analyzedObject)
            ]);

            return Types::UNKNOWN;
        }
    }
}
