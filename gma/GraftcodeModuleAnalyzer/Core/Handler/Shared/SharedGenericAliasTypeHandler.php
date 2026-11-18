<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\Handlers;
use GraftcodeProtocol\{
    Enums\IsNullable,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};

/**
 * Handler for generic type parameters (type aliases like T, K, V).
 * Note: PHP doesn't have true generics, but this handler provides
 * a modular way to handle generic aliases similar to netcore's SharedGenericAliasTypeHandler.
 */
final class SharedGenericAliasTypeHandler extends SharedTypeHandler
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $typeUsageWrapper = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE
            );

            $typeName = $this->extractTypeName($analyzedObject);
            $isNullable = IsNullable::FALSE;

            // TYPE_USAGE_GENERIC_ALIAS payload: [IS_NULLABLE, ALIAS_NAME]
            $command = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_USAGE_GENERIC_ALIAS
            );
            $command = $command->addArgToPayload($isNullable);
            $command = $command->addArgToPayload($typeName);

            return $typeUsageWrapper->addArgToPayload($command);
        } catch (Exception $e) {
            $this->logger->trackError('Error generating generic alias type model', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
