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
    Exception\ExpectedStringTypeException
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use InvalidArgumentException;
use Throwable;

final class SharedArgumentNameHandler extends BaseHandler implements HandlersInterface
{
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            $argName = match (true) {
                is_string($analyzedObject) => $analyzedObject,
                default => throw new ExpectedStringTypeException($analyzedObject)
            };

            $this->logger->trackVerbose(
                'Processing argument name: {argumentName}',
                ['argumentName' => $argName]
            );

            return $argName;
        } catch (InvalidArgumentException $e) {
            $this->logger->trackError(
                'Error processing argument name: {analyzedObject}. Error: {error}',
                [
                    'analyzedObject' => get_debug_type($analyzedObject),
                    'error' => $e->getMessage()
                ]
            );
            throw $e;

        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'context' => 'SharedArgumentNameHandler::generateUnifiedGraftModel',
                'analyzedObject' => get_debug_type($analyzedObject)
            ]);

            return Types::UNKNOWN;
        }
    }
}
