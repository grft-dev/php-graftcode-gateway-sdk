<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\{
    SharedCommentDocHandler,
    SharedMethodCompletionAnalyzerHandler,
    SharedTypeHandler
};
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use GraftcodeProtocol\{
    Enums\ModifierType,
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use ReflectionFunction;
use Throwable;

final class GlobalMethodAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        if (!is_array($analyzedObject) || !isset($analyzedObject['name'])) {
            throw new Exception('Expected array with name key for global function analysis');
        }

        $functionName = $analyzedObject['name'];
        $parameters = $analyzedObject['params'] ?? [];

        $this->logger->trackVerbose(
            'Generating UnifiedGraftModel for global function: {functionName}',
            ['functionName' => $functionName]
        );

        try {
            $globalMethodCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::GLOBAL_METHOD
            );

            // GLOBAL_METHOD payload structure: STRING:NAME, TYPE_USAGE, ENUM:MODIFIER, PARAMETERS_ARRAY
            // Add method name
            $globalMethodCommand = $globalMethodCommand->addArgToPayload(
                $handlers->SHARED_HANDLER[UnifiedGraftModelType::METHOD_NAME->value]
                    ->generateUnifiedGraftModel($functionName, $globalMethodCommand, $handlers)
            );

            // Add return type (TYPE_USAGE)
            $returnType = $this->getReturnType($handlers);
            $globalMethodCommand = $globalMethodCommand->addArgToPayload($returnType);

            // Add modifier (default to PUBLIC for global functions)
            $globalMethodCommand = $globalMethodCommand->addArgToPayload(ModifierType::PUBLIC->value);

            // Add parameters array
            if (!empty($parameters)) {
                $parametersArray = $this->buildParametersArray($parameters, $handlers, $globalMethodCommand);
                $globalMethodCommand = $globalMethodCommand->addArgToPayload($parametersArray);
            } else {
                $emptyParametersArray = new UnifiedGraftModel(
                    RuntimeName::PHP,
                    UnifiedGraftModelType::PARAMETERS_ARRAY
                );
                $globalMethodCommand = $globalMethodCommand->addArgToPayload($emptyParametersArray);
            }

            $reflection = $this->reflectGlobalFunction($functionName);
            if ($reflection !== null) {
                $globalMethodCommand = SharedMethodCompletionAnalyzerHandler::appendToPayloadIfNeeded(
                    $reflection,
                    $globalMethodCommand
                );

                $globalMethodCommand = SharedCommentDocHandler::appendToPayloadIfPresent(
                    $reflection,
                    $globalMethodCommand,
                    $handlers
                );
            }

            $this->logger->trackVerbose(
                'Successfully generated UnifiedGraftModel for global function: {functionName}',
                ['functionName' => $functionName]
            );

            return $globalMethodCommand;

        } catch (Exception $e) {
            $this->logger->trackError(
                'Failed to generate UnifiedGraftModel for global function: {functionName}, error: {error}',
                ['functionName' => $functionName, 'error' => $e->getMessage()]
            );
            throw $e;
        }
    }

    private function buildParametersArray(
        array $parameters,
        Handlers $handlers,
        UnifiedGraftModel $parentCommand //@TODO Check why param $parentCommand is not use
    ): UnifiedGraftModel {
        $parametersArray = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::PARAMETERS_ARRAY
        );

        foreach ($parameters as $paramName) {
            $parameter = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::PARAMETER,
            );

            $parameter = $parameter->addArgToPayload(
                $handlers->SHARED_HANDLER[UnifiedGraftModelType::ARGUMENT_NAME->value]
                    ->generateUnifiedGraftModel($paramName, $parameter, $handlers)
            );

            // Add parameter type (default to mixed)
            $paramType = $this->getParameterType($handlers);
            $parameter = $parameter->addArgToPayload($paramType);

            $parametersArray = $parametersArray->addArgToPayload($parameter);
        }

        return $parametersArray;
    }

    /**
     * @throws Exception
     */
    private function getReturnType(Handlers $handlers): UnifiedGraftModel
    {
        return $this->createMixedTypeUsage($handlers);
    }

    /**
     * @throws Exception
     */
    private function getParameterType(Handlers $handlers): UnifiedGraftModel
    {
        return $this->createMixedTypeUsage($handlers);
    }

    /**
     * Resolve a global function name to a ReflectionFunction for CommentDoc
     * extraction. Returns NULL when the function cannot be reflected (e.g.,
     * not yet defined / in a namespace not autoloadable).
     */
    private function reflectGlobalFunction(string $functionName): ?ReflectionFunction
    {
        if ($functionName === '' || !function_exists($functionName)) {
            return null;
        }
        try {
            return new ReflectionFunction($functionName);
        } catch (Throwable $e) {
            $this->logger->trackVerbose(
                'Could not reflect global function {name}: {reason}',
                ['name' => $functionName, 'reason' => $e->getMessage()]
            );
            return null;
        }
    }

    /**
     * @throws Exception
     */
    private function createMixedTypeUsage(Handlers $handlers): UnifiedGraftModel
    {
        return new SharedTypeHandler()->buildPrimitiveTypeUsage(Types::MIXED, 0, $handlers);
    }
}
