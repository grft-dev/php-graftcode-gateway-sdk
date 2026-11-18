<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedTypeHandler;
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};

final class GlobalFieldAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        $variableName = match (true) {
            is_string($analyzedObject) => $analyzedObject,
            is_array($analyzedObject) && isset($analyzedObject['name']) => $analyzedObject['name'],
            default => throw new Exception('Expected string or array with name key for global variable analysis')
        };

        $this->logger->trackVerbose(
            'Generating UnifiedGraftModel for global variable: {variableName}',
            ['variableName' => $variableName]
        );

        try {
            $globalFieldCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::GLOBAL_FIELD
            );

            // Add field name
            $fieldNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::FIELD_NAME->value];
            
            $globalFieldCommand = $globalFieldCommand->addArgToPayload(
                $fieldNameHandler->generateUnifiedGraftModel($variableName, $globalFieldCommand, $handlers)
            );

            // Add field type (default to mixed for PHP global variables)
            $fieldType = $this->getVariableType($variableName, $handlers);
            $globalFieldCommand = $globalFieldCommand->addArgToPayload($fieldType);

            // Add modifier (global variables are typically public in PHP context)
            $modifier = $this->getVariableModifier();
            $globalFieldCommand = $globalFieldCommand->addArgToPayload($modifier);

            $this->logger->trackVerbose(
                'Successfully generated UnifiedGraftModel for global variable: {variableName}',
                ['variableName' => $variableName]
            );

            return $globalFieldCommand;

        } catch (Exception $e) {
            $this->logger->trackError(
                'Failed to generate UnifiedGraftModel for global variable: {variableName}, error: {error}',
                ['variableName' => $variableName, 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    /**
     * Gets the variable type (simplified implementation).
     */
    private function getVariableType(string $variableName, Handlers $handlers): UnifiedGraftModel
    {
        $type = match ($variableName) {
            '_GET', '_POST', '_REQUEST', '_COOKIE', '_SESSION', 'argv', 'GLOBALS', '_FILES', '_SERVER', '_ENV' => Types::ARRAY,
            'argc' => Types::INT,
            default => Types::MIXED
        };

        return new SharedTypeHandler()->buildPrimitiveTypeUsage($type, 0, $handlers);
    }

    private function getVariableModifier(): UnifiedGraftModel
    {
        // Global variables in PHP are essentially public
        return new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::MODIFIER,
            [1] // Assuming 1 represents public modifier
        );
    }
}
