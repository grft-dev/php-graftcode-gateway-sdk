<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeModuleAnalyzer\Core\Handler\Shared\SharedCommentDocHandler;
use GraftcodeModuleAnalyzer\Utils\Const\CustomTypes;
use GraftcodeProtocol\{
    Enums\RuntimeName,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use InvalidArgumentException;
use ReflectionMethod;
use Throwable;

final class CreateInstanceAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Throwable
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->validateAnalyzedObject($analyzedObject);

            /** @var ReflectionMethod $analyzedObject */
            $className = $analyzedObject->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS;
            $handlers->CURRENT_DECLARING_TYPE_FQCN = $className;
            $handlers->CURRENT_REFLECTION_METHOD = $analyzedObject;
            $handlers->CURRENT_REFLECTION_PARAMETER_NAME = '';

            $this->logger->trackVerbose(
                'Generating UnifiedGraftModel for constructor in class: {className}',
                ['className' => $className]
            );

            $createInstanceCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::CONSTRUCTOR
            );

            $createInstanceCommand = $this->addClassNameToPayload(
                $createInstanceCommand,
                $parentCommand,
                $handlers
            );

            $createInstanceCommand = $this->addModifiersToPayload(
                $createInstanceCommand,
                $analyzedObject,
                $handlers
            );

            $this->logger->trackVerbose(
                'Adding parameters array to payload for constructor in class: {className}',
                ['className' => $className]
            );

            /** @var HandlersInterface $parametersHandler */
            $parametersHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::PARAMETERS_ARRAY->value];
            $createInstanceCommand = $createInstanceCommand->addArgToPayload(
                $parametersHandler->generateUnifiedGraftModel($analyzedObject, $createInstanceCommand, $handlers)
            );

            $createInstanceCommand = SharedCommentDocHandler::appendToPayloadIfPresent(
                $analyzedObject,
                $createInstanceCommand,
                $handlers
            );

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for constructor in class: {className}',
                ['className' => $className]
            );

            return $createInstanceCommand;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for constructor',
                'analyzedObject' => get_class($analyzedObject)
            ]);

            throw $e;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateAnalyzedObject(object $analyzedObject): void
    {
        if (!$analyzedObject instanceof ReflectionMethod) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected ReflectionMethod for constructor analysis, got %s',
                    get_class($analyzedObject)
                )
            );
        }

        if (!$analyzedObject->isConstructor()) {
            throw new InvalidArgumentException(
                'ReflectionMethod must represent a constructor (__construct)'
            );
        }
    }
    /**
     * @throws Throwable
     */
    private function addClassNameToPayload(
        UnifiedGraftModel $command,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $this->logger->trackVerbose('Adding class name to payload from parent command');

            // Extract class name from parent command payload
            $parentPayload = $parentCommand->getPayload();
            $classNameData = $parentPayload[1] ?? $parentPayload[0] ?? CustomTypes::UNKNOWN_CLASS;

            // Extract string from class name data (it might be an object with className property)
            $className = match (true) {
                is_string($classNameData) => $classNameData,
                is_object($classNameData) && property_exists($classNameData, 'className') => $classNameData->className,
                default => CustomTypes::UNKNOWN_CLASS
            };

            /** @var HandlersInterface $classNameHandler */
            $classNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::CLASS_NAME->value];

            return $command->addArgToPayload(
                $classNameHandler->generateUnifiedGraftModel($className, $command, $handlers)
            );
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding class name to constructor payload'
            ]);

            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    private function addModifiersToPayload(
        UnifiedGraftModel $command,
        ReflectionMethod $constructor,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $className = $constructor->getDeclaringClass()?->getName() ?? CustomTypes::UNKNOWN_CLASS;
            $modifiers = $constructor->getModifiers();

            $this->logger->trackVerbose(
                'Adding modifiers to payload for constructor in class: {className}, Modifiers: {modifiers}',
                [
                    'className' => $className,
                    'modifiers' => $modifiers
                ]
            );

            /** @var HandlersInterface $modifierHandler */
            $modifierHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODIFIER->value];

            return $command->addArgToPayload(
                $modifierHandler->generateUnifiedGraftModel($constructor, $command, $handlers)
            );
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error adding modifiers to constructor payload',
                'constructor' => $constructor->getName()
            ]);

            throw $e;
        }
    }
}
