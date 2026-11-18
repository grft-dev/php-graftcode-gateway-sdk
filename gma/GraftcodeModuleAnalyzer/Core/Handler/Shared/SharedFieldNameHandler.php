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

final class SharedFieldNameHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            $this->logger->trackVerbose(sprintf(
                'Processing field name: {%s}',
                LoggerUtils::getLogSafeValue($analyzedObject)
                ),
                ['processing field name' => LoggerUtils::getLogValue($analyzedObject)]
            );

            // Handle different input types for field name extraction
            $fieldName = match (true) {
                is_string($analyzedObject) => $analyzedObject,
                is_array($analyzedObject) && isset($analyzedObject['name']) => $analyzedObject['name'],
                is_object($analyzedObject) && property_exists($analyzedObject, 'name') => $analyzedObject->name,
                is_object($analyzedObject) && method_exists($analyzedObject, 'getName') => $analyzedObject->getName(),
                default => (string) $analyzedObject
            };

            $this->logger->trackVerbose(
                'Extracted field name: {fieldName}',
                ['fieldName' => $fieldName]
            );

            return $fieldName;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'error' => 'Error processing field name',
                'type' => gettype($analyzedObject)
            ]);

            return Types::UNKNOWN;
        }
    }
}
