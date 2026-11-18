<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftcodeModuleAnalyzer\Utils\Const\SpecialChars;
use GraftcodeProtocol\{
    Enums\InheritanceKind,
    Enums\ModifierType,
    Enums\RuntimeName,
    Enums\TypeKind,
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use GraftcodeModuleAnalyzer\Core\Analysis\MethodFilterHelper;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\MethodDispatch;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

final class GetTypeAnalyzerHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        if (!$analyzedObject instanceof ReflectionClass) {
            throw new Exception('Expected ReflectionClass, got ' . get_class($analyzedObject));
        }

        $clazz = $analyzedObject;
        $handlers->CURRENT_DECLARING_TYPE_FQCN = $clazz->getName();

        $this->logger->trackVerbose(
            'Generating UnifiedGraftModel for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        try {
            $getTypeCommand = new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_DEF
            );

            // Build the complete type definition by adding all components
            $getTypeCommand = $this->addNamespaceToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addClassNameToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addModuleFullNameToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addModuleHashToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addModifierToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addGenericParametersToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addTypeDefKindToPayload($getTypeCommand, $clazz);
            $getTypeCommand = $this->addTypeInheritanceToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addFieldsToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addMethodsToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addStringCastMethodsToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addConstructorsToPayload($getTypeCommand, $clazz, $handlers);
            $getTypeCommand = $this->addNestedTypesToPayload($getTypeCommand, $clazz, $handlers);

            $this->logger->trackVerbose(
                'Completed generating UnifiedGraftModel for type: {typeName}',
                ['typeName' => $clazz->getName()]
            );

            return $getTypeCommand;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Error generating UnifiedGraftModel for type',
                'typeName' => $clazz->getName()
            ]);

            throw new Exception('Failed to generate unified graft model for type {$clazz->getName()}', previous: $e);
        }
    }

    private function addNamespaceToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $namespaceName = $clazz->getNamespaceName() ?: Types::UNKNOWN;

        $this->logger->trackVerbose(
            'Adding namespace to payload: ' . $namespaceName,
            ['namespace' => $namespaceName]
        );

        $namespaceHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::NAMESPACE->value];

        return $command->addArgToPayload(
            $namespaceHandler->generateUnifiedGraftModel(
                $namespaceName,
                $command,
                $handlers
            )
        );
    }

    private function addClassNameToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $className = $clazz->getShortName();

        $this->logger->trackVerbose(
            'Adding class name to payload: ' . $className,
            ['className' => $className]
        );

        $classNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::CLASS_NAME->value];

        return $command->addArgToPayload(
            $classNameHandler->generateUnifiedGraftModel(
                $className,
                $command,
                $handlers
            )
        );
    }

    private function addModuleFullNameToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $this->logger->trackVerbose(
            'Adding module full name to payload for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        $moduleFullNameHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODULE_FULL_NAME->value];

        return $command->addArgToPayload(
            $moduleFullNameHandler->generateUnifiedGraftModel(
                $clazz,
                $command,
                $handlers
            )
        );
    }

    private function addModuleHashToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $this->logger->trackVerbose(
            'Adding module hash to payload for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        $moduleHashHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODULE_HASH->value];

        return $command->addArgToPayload(
            $moduleHashHandler->generateUnifiedGraftModel(
                $clazz,
                $command,
                $handlers
            )
        );
    }

    private function addModifierToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $modifiers = $clazz->getModifiers();

        $this->logger->trackVerbose(
            'Adding modifier to payload for type: {typeName}, modifiers: {modifiers}',
            ['typeName' => $clazz->getName(), 'modifiers' => $modifiers]
        );

        $modifierHandler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::MODIFIER->value];

        return $command->addArgToPayload(
            $modifierHandler->generateUnifiedGraftModel(
                $clazz,
                $command,
                $handlers
            )
        );
    }

    private function addGenericParametersToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers //@TODO Check why param $handlers is not use
    ): UnifiedGraftModel {
        $this->logger->trackVerbose(
            'Adding generic parameters to payload for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        // PHP doesn't have true generics, so GENERIC_PARAMETERS will be empty array
        // But we still need to add the GENERIC_PARAMETERS structure per specification
        $genericParameters = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::GENERIC_PARAMETERS
        );

        // GENERIC_PARAMETERS payload: TYPE_USAGE[]
        // For PHP, this will typically be empty, but structure must exist
        return $command->addArgToPayload($genericParameters);
    }

    private function addTypeDefKindToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz
    ): UnifiedGraftModel {
        $typeKind = match (true) {
            $clazz->isInterface() => TypeKind::INTERFACE,
            $clazz->isAbstract() && !$clazz->isInterface() => TypeKind::ABSTRACT_CLASS,
            default => null,
        };

        if ($typeKind === null) {
            return $command;
        }

        return $command->addArgToPayload(
            new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::TYPE_DEF_KIND,
                [$typeKind->value]
            )
        );
    }

    private function addTypeInheritanceToPayload(
        UnifiedGraftModel $command,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $inheritance = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_INHERITANCE
        );

        $baseClass = $clazz->getParentClass();
        if ($baseClass instanceof ReflectionClass && $baseClass->getName() !== 'stdClass') {
            $inheritance = $this->addInheritanceEntry(
                $inheritance,
                $baseClass,
                InheritanceKind::BASE_CLASS,
                $handlers
            );
        }

        foreach ($clazz->getInterfaceNames() as $interfaceName) {
            if (!class_exists($interfaceName) && !interface_exists($interfaceName)) {
                continue;
            }
            $interfaceReflection = new ReflectionClass($interfaceName);
            if ($interfaceReflection->isInternal()) {
                continue;
            }
            $inheritance = $this->addInheritanceEntry(
                $inheritance,
                $interfaceReflection,
                InheritanceKind::INTERFACE,
                $handlers
            );
        }

        if ($inheritance->getPayloadSize() === 0) {
            return $command;
        }

        $this->logger->trackVerbose(
            'Adding TYPE_INHERITANCE with {count} entries for type: {typeName}',
            ['count' => $inheritance->getPayloadSize(), 'typeName' => $clazz->getName()]
        );

        return $command->addArgToPayload($inheritance);
    }

    private function addInheritanceEntry(
        UnifiedGraftModel $inheritance,
        ReflectionClass $inheritedType,
        InheritanceKind $inheritanceKind,
        Handlers $handlers
    ): UnifiedGraftModel {
        $typeUsage = $handlers->SHARED_HANDLER[UnifiedGraftModelType::TYPE_USAGE->value]
            ->generateUnifiedGraftModel($inheritedType, $inheritance, $handlers);

        $entry = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_INHERITANCE_ENTRY,
            [$inheritanceKind->value, $typeUsage]
        );

        return $inheritance->addArgToPayload($entry);
    }

    private function addFieldsToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        foreach ($clazz->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $this->logger->trackVerbose(
                'Adding public constant: {constantName}',
                ['constantName' => $constant->getName()]
            );
            $getTypeCommand = $this->addStaticConstantToPayload($getTypeCommand, $constant, $handlers);
        }

        $properties = $clazz->getProperties(ReflectionProperty::IS_PUBLIC);

        $this->logger->trackVerbose(
            'Adding {count} fields to payload for type: {typeName}',
            ['count' => count($properties), 'typeName' => $clazz->getName()]
        );

        foreach ($properties as $property) {
            // Skip special properties (like those starting with __)
            if (str_starts_with($property->getName(), SpecialChars::PREFIX_MAGIC_METHOD)) {
                continue;
            }

            if ($property->isStatic()) {
                $this->logger->trackVerbose(
                    'Adding static field: {fieldName}',
                    ['fieldName' => $property->getName()]
                );
                $getTypeCommand = $this->addStaticFieldToPayload($getTypeCommand, $property, $handlers);
            } else {
                $this->logger->trackVerbose(
                    'Adding instance field: {fieldName}',
                    ['fieldName' => $property->getName()]
                );
                $getTypeCommand = $this->addInstanceFieldToPayload($getTypeCommand, $property, $handlers);
            }
        }

        return $getTypeCommand;
    }

    private function addMethodsToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $methods = $clazz->getMethods(ReflectionMethod::IS_PUBLIC);

        $this->logger->trackVerbose(
            'Adding {count} methods to payload for type: {typeName}',
            ['count' => count($methods), 'typeName' => $clazz->getName()]
        );

        foreach ($methods as $method) {
            // Skip constructors, destructors, and magic methods
            if ($method->isConstructor() || $method->isDestructor() || str_starts_with($method->getName(), SpecialChars::PREFIX_MAGIC_METHOD)) {
                continue;
            }

            if (!MethodFilterHelper::matchesFilter($method->getName(), $handlers->METHOD_FILTER, $clazz->getName())) {
                continue;
            }

            if ($method->isStatic()) {
                $this->logger->trackVerbose(
                    'Adding static method: {methodName}',
                    ['methodName' => $method->getName()]
                );
                $getTypeCommand = $this->addStaticMethodToPayload($getTypeCommand, $method, $handlers);
            } else {
                $this->logger->trackVerbose(
                    'Adding instance method: {methodName}',
                    ['methodName' => $method->getName()]
                );
                $getTypeCommand = $this->addInstanceMethodToPayload($getTypeCommand, $method, $handlers);
            }
        }

        return $getTypeCommand;
    }

    /**
     * @throws Throwable
     */
    private function addStringCastMethodsToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        if (!$clazz->hasMethod('__toString')) {
            return $getTypeCommand;
        }

        $toString = $clazz->getMethod('__toString');
        if (!$toString->isPublic()
            || !MethodFilterHelper::matchesFilter('__toString', $handlers->METHOD_FILTER, $clazz->getName())) {
            return $getTypeCommand;
        }

        $this->logger->trackVerbose(
            'Adding __toString to payload for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        return $this->addInstanceMethodToPayload($getTypeCommand, $toString, $handlers);
    }

    /**
     * @throws Throwable
     */
    private function addConstructorsToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        $constructor = $clazz->getConstructor();

        if ($constructor !== null) {
            if ($constructor->isPublic()
                && MethodFilterHelper::matchesFilter('__construct', $handlers->METHOD_FILTER, $clazz->getName())) {
                $this->logger->trackVerbose(
                    'Adding constructor to payload for type: {typeName}',
                    ['typeName' => $clazz->getName()]
                );

                return $this->addConstructorToPayload($getTypeCommand, $constructor, $handlers);
            }

            $this->logger->trackVerbose(
                'Skipping non-public constructor for type: {typeName}',
                ['typeName' => $clazz->getName()]
            );

            return $getTypeCommand;
        }

        if ($clazz->isInterface() || $clazz->isTrait() || $clazz->isEnum()) {
            $this->logger->trackVerbose(
                'Skipping implicit constructor for non-class type: {typeName}',
                ['typeName' => $clazz->getName()]
            );

            return $getTypeCommand;
        }

        $this->logger->trackVerbose(
            'Inferring implicit default constructor for type: {typeName}',
            ['typeName' => $clazz->getName()]
        );

        if (!MethodFilterHelper::matchesFilter('__construct', $handlers->METHOD_FILTER, $clazz->getName())) {
            return $getTypeCommand;
        }

        $emptyParametersArray = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::PARAMETERS_ARRAY
        );

        return $getTypeCommand->addArgToPayload(
            new UnifiedGraftModel(
                RuntimeName::PHP,
                UnifiedGraftModelType::CONSTRUCTOR,
                [
                    $clazz->getShortName(),
                    ModifierType::PUBLIC->value,
                    $emptyParametersArray,
                ]
            )
        );
    }

    private function addStaticConstantToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClassConstant $constant,
        Handlers $handlers
    ): UnifiedGraftModel {
        $staticFieldHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::STATIC_FIELD->value];

        return $getTypeCommand->addArgToPayload(
            $staticFieldHandler->generateUnifiedGraftModel(
                $constant,
                $getTypeCommand,
                $handlers
            )
        );
    }

    private function addNestedTypesToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionClass $clazz,
        Handlers $handlers
    ): UnifiedGraftModel {
        // In PHP, we can analyze traits and interfaces used by the class
        $traits = $clazz->getTraits();
        $interfaces = $clazz->getInterfaces();

        $this->logger->trackVerbose(
            'Adding {traitCount} traits and {interfaceCount} interfaces to payload for type: {typeName}',
            [
                'traitCount' => count($traits),
                'interfaceCount' => count($interfaces),
                'typeName' => $clazz->getName()
            ]
        );

        foreach ($traits as $trait) {
            if ($this->shouldSkipNestedType($trait, $clazz, $handlers)) {
                $this->logger->trackVerbose(
                    'Skipping nested trait: {traitName}',
                    ['traitName' => $trait->getName()]
                );
                continue;
            }
            $this->logger->trackVerbose(
                'Adding trait: {traitName} to payload',
                ['traitName' => $trait->getName()]
            );

            $typeDefHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::TYPE_DEF->value];
            $nestedModel = $typeDefHandler->generateUnifiedGraftModel($trait, $getTypeCommand, $handlers);
            $getTypeCommand = $getTypeCommand->addArgToPayload($nestedModel);
        }

        foreach ($interfaces as $interface) {
            if ($this->shouldSkipNestedType($interface, $clazz, $handlers)) {
                $this->logger->trackVerbose(
                    'Skipping nested interface: {interfaceName}',
                    ['interfaceName' => $interface->getName()]
                );
                continue;
            }

            $this->logger->trackVerbose(
                'Adding interface: {interfaceName} to payload',
                ['interfaceName' => $interface->getName()]
            );

            $typeDefHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::TYPE_DEF->value];
            $nestedModel = $typeDefHandler->generateUnifiedGraftModel($interface, $getTypeCommand, $handlers);
            $getTypeCommand = $getTypeCommand->addArgToPayload($nestedModel);
        }

        return $getTypeCommand;
    }

    private function shouldSkipNestedType(
        ReflectionClass $nested,
        ReflectionClass $declaringClass,
        Handlers $handlers,
    ): bool {
        // Option 1: skip built-in/internal symbols (Stringable, Traversable, etc.)
        if ($nested->isInternal()) {
            return true;
        }

        // Option 2: skip anything that doesn't come from analyzed package root
        $root = $handlers->ANALYSIS_ROOT_PATH;
        if ($root === '') {
            return false;
        }

        $nestedFile = $nested->getFileName();
        if (false === $nestedFile || $nestedFile === '') {
            return true;
        }

        $nestedReal = realpath($nestedFile) ?: $nestedFile;
        $rootReal = realpath($root) ?: $root;

        if (false === $this->isPathInside($nestedReal, $rootReal)) {
            return true;
        }

        // Option 3: skip interfaces/traits from separate files already scheduled as top-level TYPE_DEF
        $declaringFile = $declaringClass->getFileName();
        if (false === $declaringFile || $declaringFile === '') {
            return false;
        }

        $declaringReal = realpath($declaringFile) ?: $declaringFile;
        if ($declaringReal === $nestedReal) {
            return false;
        }

        return in_array($nested->getName(), $handlers->DISCOVERED_TYPE_FQCNS, true);
    }

    private function isPathInside(string $candidatePath, string $rootPath): bool
    {
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rootPath), DIRECTORY_SEPARATOR);
        $candidate = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidatePath), DIRECTORY_SEPARATOR);

        // Case-insensitive compare on Windows, case-sensitive otherwise
        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }

        // Ensure prefix match on directory boundary
        if ($candidate === $root) {
            return true;
        }

        return str_starts_with($candidate, $root . DIRECTORY_SEPARATOR);
    }

    private function addMethodToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        $nodeType = MethodDispatch::resolveNodeType($method);
        $methodHandler = $handlers->ANALYZER_HANDLER[$nodeType->value];

        return $getTypeCommand->addArgToPayload(
            $methodHandler->generateUnifiedGraftModel(
                $method,
                $getTypeCommand,
                $handlers
            )
        );
    }

    private function addInstanceMethodToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        return $this->addMethodToPayload($getTypeCommand, $method, $handlers);
    }

    private function addStaticMethodToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionMethod $method,
        Handlers $handlers
    ): UnifiedGraftModel {
        return $this->addMethodToPayload($getTypeCommand, $method, $handlers);
    }

    /**
     * @throws Throwable
     */
    private function addConstructorToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionMethod $constructor,
        Handlers $handlers
    ): UnifiedGraftModel {
        $constructorHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::CONSTRUCTOR->value];

        return $getTypeCommand->addArgToPayload(
            /** @var $constructorHandler  CreateInstanceAnalyzerHandler*/
            $constructorHandler->generateUnifiedGraftModel(
                $constructor,
                $getTypeCommand,
                $handlers
            )
        );
    }

    private function addInstanceFieldToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionProperty $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        $instanceFieldHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::INSTANCE_FIELD->value];

        return $getTypeCommand->addArgToPayload(
            $instanceFieldHandler->generateUnifiedGraftModel(
                $field,
                $getTypeCommand,
                $handlers
            )
        );
    }

    private function addStaticFieldToPayload(
        UnifiedGraftModel $getTypeCommand,
        ReflectionProperty $field,
        Handlers $handlers
    ): UnifiedGraftModel {
        $staticFieldHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::STATIC_FIELD->value];

        return $getTypeCommand->addArgToPayload(
            $staticFieldHandler->generateUnifiedGraftModel(
                $field,
                $getTypeCommand,
                $handlers
            )
        );
    }
}
