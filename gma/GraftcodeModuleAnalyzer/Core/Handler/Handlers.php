<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use ReflectionMethod;
use GraftcodeModuleAnalyzer\Core\Handler\Shared\{
    SharedArgumentNameHandler,
    SharedCommentDocHandler,
    SharedTypeHandler,
    SharedClassNameHandler,
    SharedMethodNameHandler,
    SharedModifierHandler,
    SharedModuleHashHandler,
    SharedModuleFullNameHandler,
    SharedNamespaceHandler,
    SharedParametersArrayHandler,
    SharedFieldNameHandler,
    SharedPrimitiveTypeHandler,
    SharedArrayTypeHandler,
    SharedGenericTypeHandler,
    SharedGenericAliasTypeHandler,
    SharedVoidTypeHandler,
};
use GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\Sections\{
    CommentDeprecatedSectionHandler,
    CommentDescriptionSectionHandler,
    CommentExamplesSectionHandler,
    CommentLinksSectionHandler,
    CommentMetadataSectionHandler,
    CommentParametersSectionHandler,
    CommentReturnsSectionHandler,
    CommentSourceSectionHandler,
    CommentSummarySectionHandler,
    CommentThrowsSectionHandler,
};
use GraftcodeProtocol\Enums\UnifiedGraftModelType;

final class Handlers
{
    /** @var array<int UnifiedGraftModelType, object> */
    public readonly array $ANALYZER_HANDLER;

    /** @var array<int UnifiedGraftModelType, object> */
    public readonly array $SHARED_HANDLER;

    /** @var list<object> Ordered CommentDoc section builders. */
    public readonly array $COMMENT_DOC_SECTION_HANDLERS;

    public array $TYPES_TO_ANALYZE = [];

    /** @var list<string> FQCNs discovered for top-level TYPE_DEF emission in the current analysis run. */
    public array $DISCOVERED_TYPE_FQCNS = [];

    public string $ANALYSIS_ROOT_PATH = '';
    public string $METHOD_FILTER = '';

    /** Composer package name (vendor/package) for the analyzed library. */
    public string $LIBRARY_ASSEMBLY_NAME = '';

    /** Composer package version for the analyzed library. */
    public string $LIBRARY_ASSEMBLY_VERSION = '';

    /** @var list<string> PSR-4 root namespaces from composer.json autoload. */
    public array $LIBRARY_PSR4_ROOTS = [];

    /** FQCN of the type currently being analyzed (used to resolve self/static/parent). */
    public string $CURRENT_DECLARING_TYPE_FQCN = '';

    /** Method whose return/parameter types are currently being analyzed. */
    public ?ReflectionMethod $CURRENT_REFLECTION_METHOD = null;

    /** Parameter name when analyzing a method parameter type from PHPDoc. */
    public string $CURRENT_REFLECTION_PARAMETER_NAME = '';

    public function __construct()
    {
        $this->ANALYZER_HANDLER = [
            UnifiedGraftModelType::TYPE_DEF->value => new GetTypeAnalyzerHandler(),
            UnifiedGraftModelType::INSTANCE_METHOD->value => new InvokeInstanceMethodAnalyzerHandler(),
            UnifiedGraftModelType::ABSTRACT_INSTANCE_METHOD->value => new InvokeAbstractInstanceMethodAnalyzerHandler(),
            UnifiedGraftModelType::STATIC_METHOD->value => new InvokeStaticMethodAnalyzerHandler(),
            UnifiedGraftModelType::ABSTRACT_STATIC_METHOD->value => new InvokeAbstractStaticMethodAnalyzerHandler(),
            UnifiedGraftModelType::STATIC_FIELD->value => new GetStaticFieldAnalyzerHandler(),
            UnifiedGraftModelType::CONSTRUCTOR->value => new CreateInstanceAnalyzerHandler(),
            UnifiedGraftModelType::INSTANCE_FIELD->value => new GetInstanceFieldAnalyzerHandler(),
            UnifiedGraftModelType::PROPERTY->value => new PropertyAnalyzerHandler(),
            UnifiedGraftModelType::GLOBAL_METHOD->value => new GlobalMethodAnalyzerHandler(),
            UnifiedGraftModelType::GLOBAL_FIELD->value => new GlobalFieldAnalyzerHandler(),
        ];

        $this->SHARED_HANDLER = [
            UnifiedGraftModelType::ARGUMENT_NAME->value => new SharedArgumentNameHandler(),
            UnifiedGraftModelType::CLASS_NAME->value => new SharedClassNameHandler(),
            UnifiedGraftModelType::METHOD_NAME->value => new SharedMethodNameHandler(),
            UnifiedGraftModelType::MODIFIER->value => new SharedModifierHandler(),
            UnifiedGraftModelType::TYPE_USAGE->value => new SharedTypeHandler(),
            UnifiedGraftModelType::PARAMETERS_ARRAY->value => new SharedParametersArrayHandler(),
            UnifiedGraftModelType::NAMESPACE->value => new SharedNamespaceHandler(),
            UnifiedGraftModelType::MODULE_FULL_NAME->value => new SharedModuleFullNameHandler(),
            UnifiedGraftModelType::MODULE_HASH->value => new SharedModuleHashHandler(),
            UnifiedGraftModelType::FIELD_NAME->value => new SharedFieldNameHandler(),
            // Specialized type usage handlers (nested structure matching netcore/JVM/Python/Node.js)
            UnifiedGraftModelType::TYPE_USAGE_PRIMITIVE->value => new SharedPrimitiveTypeHandler(),
            UnifiedGraftModelType::TYPE_USAGE_ARRAY->value => new SharedArrayTypeHandler(),
            UnifiedGraftModelType::TYPE_USAGE_GENERIC->value => new SharedGenericTypeHandler(),
            UnifiedGraftModelType::TYPE_USAGE_GENERIC_ALIAS->value => new SharedGenericAliasTypeHandler(),
            UnifiedGraftModelType::TYPE_USAGE_VOID->value => new SharedVoidTypeHandler(),
            // CommentDoc (UGM v1.4.0) - structured documentation comments.
            UnifiedGraftModelType::COMMENT_DOC->value => new SharedCommentDocHandler(),
        ];

        $this->COMMENT_DOC_SECTION_HANDLERS = [
            new CommentSummarySectionHandler(),
            new CommentDescriptionSectionHandler(),
            new CommentParametersSectionHandler(),
            new CommentReturnsSectionHandler(),
            new CommentThrowsSectionHandler(),
            new CommentExamplesSectionHandler(),
            new CommentLinksSectionHandler(),
            new CommentDeprecatedSectionHandler(),
            new CommentMetadataSectionHandler(),
            new CommentSourceSectionHandler(),
        ];
    }
}
