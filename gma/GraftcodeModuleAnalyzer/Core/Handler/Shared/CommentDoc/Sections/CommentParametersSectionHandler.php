<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\Sections;

use GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\{
    CommentDocBuildContext,
    CommentDocSectionHandlerInterface,
    CommentDocUgmFactory
};
use GraftcodeProtocol\CommentDoc\{
    InlineMarkup,
    TypeMapping
};
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use ReflectionFunctionAbstract;
use Throwable;

final class CommentParametersSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $paramNodes = [];
        $defaults = $this->collectDefaults($context->reflection);

        foreach (($context->parsed['params'] ?? []) as $param) {
            $name = (string)($param['name'] ?? '');
            $rawType = (string)($param['type'] ?? '');
            $description = (string)($param['description'] ?? '');
            $defaultValue = $defaults[$name] ?? '';
            $isRequired = !array_key_exists($name, $defaults);

            $paramNodes[] = CommentDocUgmFactory::node(
                UnifiedGraftModelType::COMMENT_PARAMETER,
                [
                    $name,
                    $rawType,
                    TypeMapping::phpRawToNormalized($rawType),
                    InlineMarkup::PHPDOC->encode($description),
                    $isRequired,
                    str_contains($rawType, 'null'),
                    $defaultValue,
                ]
            );
        }

        if ($paramNodes === []) {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_PARAMETERS_ARRAY,
            $paramNodes
        );
    }

    /**
     * @return array<string,string>
     */
    private function collectDefaults(ReflectionFunctionAbstract $reflection): array
    {
        $defaults = [];
        try {
            foreach ($reflection->getParameters() as $parameter) {
                if ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                    $defaults[$parameter->getName()] = is_scalar($value) || $value === null
                        ? var_export($value, true)
                        : '';
                }
            }
        } catch (Throwable) {
            // Reflection occasionally throws on internal functions; skip defaults.
        }

        return $defaults;
    }
}
