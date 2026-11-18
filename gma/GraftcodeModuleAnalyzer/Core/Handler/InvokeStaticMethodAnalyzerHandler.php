<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedCommentDocHandler;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedMethodCompletionAnalyzerHandler;
use GraftcodeModuleAnalyzer\Utils\{
    Const\CustomTypes,
    Const\Types,
    Exception\InvokeStaticMethodAnalyzerHandlerException,
};
use GraftcodeProtocol\Enums\{
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\{
    UnifiedGraftModel,
    UnifiedGraftModelInterface
};
use ReflectionMethod;
use Throwable;

class InvokeStaticMethodAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    protected function getMethodType(ReflectionMethod $analyzedMethod): UnifiedGraftModelType
    {
        return UnifiedGraftModelType::STATIC_METHOD;
    }

    /**
     * @throws Exception|InvokeStaticMethodAnalyzerHandlerException
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->validateAnalyzedObject($analyzedObject);

            /** @var ReflectionMethod $analyzedMethod */
            $analyzedMethod = $analyzedObject;
            $methodName = $analyzedMethod->getName();

            $this->logger->trackVerbose(
                'Generating UnifiedGraftModel for static method: {methodName} in class: {className}',
                [
                    'methodName' => $analyzedMethod->getName(),
                    'className' => $analyzedMethod->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS
                ]
            );

            $handlers->CURRENT_DECLARING_TYPE_FQCN = $analyzedMethod->getDeclaringClass()->getName();
            $handlers->CURRENT_REFLECTION_METHOD = $analyzedMethod;
            $handlers->CURRENT_REFLECTION_PARAMETER_NAME = '';

            $invokeStaticMethodCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                $this->getMethodType($analyzedMethod)
            );

            $invokeStaticMethodCommand = $this->addMethodNameToPayload(
                $invokeStaticMethodCommand,
                $analyzedMethod
            );

            $invokeStaticMethodCommand = $this->addReturnTypeToPayload(
                $invokeStaticMethodCommand,
                $analyzedMethod,
                $handlers
            );

            $invokeStaticMethodCommand = $this->addModifiersToPayload(
                $invokeStaticMethodCommand,
                $analyzedMethod,
                $handlers
            );

            $invokeStaticMethodCommand = $this->addParametersToPayload(
                $invokeStaticMethodCommand,
                $analyzedMethod,
                $handlers
            );

            $invokeStaticMethodCommand = $this->addAttributesToPayload(
                $invokeStaticMethodCommand,
                $analyzedMethod
            );

            $invokeStaticMethodCommand = SharedMethodCompletionAnalyzerHandler::appendToPayloadIfNeeded(
                $analyzedMethod,
                $invokeStaticMethodCommand
            );

            $invokeStaticMethodCommand = SharedCommentDocHandler::appendToPayloadIfPresent(
                $analyzedMethod,
                $invokeStaticMethodCommand,
                $handlers
            );

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for static method: {methodName}',
                ['methodName' => $methodName]
            );

            return $invokeStaticMethodCommand;

        } catch (Throwable $e) {
            $this->logger->trackError('Error generating UnifiedGraftModel for static method', [
                'analyzedObject' => get_class($analyzedObject),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new InvokeStaticMethodAnalyzerHandlerException($e);
        }
    }

    /**
     * @throws Exception
     */
    private function validateAnalyzedObject(object $analyzedObject): void
    {
        if (!$analyzedObject instanceof ReflectionMethod) {
            throw new Exception(sprintf(
                'Expected ReflectionMethod, got %s',
                get_class($analyzedObject)
            ));
        }

        if (!$analyzedObject->isStatic()) {
            throw new Exception(sprintf(
                'Method %s::%s is not static',
                $analyzedObject->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS,
                $analyzedObject->getName()
            ));
        }
    }

    private function addMethodNameToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method
    ): UnifiedGraftModel {
        $methodName = $method->getName();
        
        $this->logger->trackVerbose(
            'Adding method name to payload: {methodName}',
            ['methodName' => $methodName]
        );

        return $command->addArgToPayload($methodName);
    }

    private function addReturnTypeToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModelInterface {
        $returnType = $method->getReturnType();

        $this->logger->trackVerbose(
            'Adding return type to payload: {returnType} for method: {methodName}',
            [
                'returnType' => $returnType?->getName() ?? Types::MIXED,
                'methodName' => $method->getName()
            ]
        );

       /** @var HandlersInterface $typeHandler */
        $typeHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value];

        return $command->addArgToPayload(
            $typeHandler->generateUnifiedGraftModel(
                $returnType ?? (object)['type' => Types::MIXED],
                $command,
                $handlers
            )
        );
    }

    private function addModifiersToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $modifiers = $method->getModifiers();
        
        $this->logger->trackVerbose(
            'Adding modifiers to payload for method: {methodName}, modifiers: {modifiers}',
            [
                'methodName' => $method->getName(),
                'modifiers' => $modifiers
            ]
        );

        /** @var HandlersInterface $modifierHandler */
        $modifierHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODIFIER->value];
        
        return $command->addArgToPayload(
            $modifierHandler->generateUnifiedGraftModel($method, $command, $handlers)
        );
    }

    private function addParametersToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $parametersCount = $method->getNumberOfParameters();
        
        $this->logger->trackVerbose(
            'Adding parameters array to payload for method: {methodName}, parameters count: {count}',
            [
                'methodName' => $method->getName(),
                'count' => $parametersCount
            ]
        );

        /** @var HandlersInterface $parametersHandler */
        $parametersHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::PARAMETERS_ARRAY->value];
        
        return $command->addArgToPayload(
            $parametersHandler->generateUnifiedGraftModel($method, $command, $handlers)
        );
    }

    private function addAttributesToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $method
    ): UnifiedGraftModel {
        if (method_exists($method, 'getAttributes')) {
            $attributes = $method->getAttributes();
            
            if (!empty($attributes)) {
                $attributeNames = array_map(
                    static fn($attr) => $attr->getName(),
                    $attributes
                );
                
                $this->logger->trackVerbose(
                    'Adding attributes to payload for method: {methodName}, attributes: {attributes}',
                    [
                        'methodName' => $method->getName(),
                        'attributes' => implode(', ', $attributeNames)
                    ]
                );

                foreach ($attributeNames as $attributeName) {
                    $command = $command->addArgToPayload($attributeName);
                }
            }
        }

        return $command;
    }
}
