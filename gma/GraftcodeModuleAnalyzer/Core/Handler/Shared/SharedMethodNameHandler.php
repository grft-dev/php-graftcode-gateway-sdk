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

final class SharedMethodNameHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            $this->logger->trackVerbose(sprintf(
                'Processing method name: {%s}',
                LoggerUtils::getLogSafeValue($analyzedObject)
                ), ['processing method name' => LoggerUtils::getLogValue($analyzedObject)]
            );

            // Convert analyzed object to string to ensure serializable output
            return match (true) {
                is_string($analyzedObject) => $analyzedObject,
                is_object($analyzedObject) && method_exists($analyzedObject, 'getName') => $analyzedObject->getName(),
                is_object($analyzedObject) && method_exists($analyzedObject, '__toString') => (string) $analyzedObject,
                is_object($analyzedObject) => get_class($analyzedObject),
                default => (string) $analyzedObject
            };
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'error' => 'Error processing method name',
                'type' => gettype($analyzedObject)
            ]);

            return Types::UNKNOWN;
        }
    }
}
