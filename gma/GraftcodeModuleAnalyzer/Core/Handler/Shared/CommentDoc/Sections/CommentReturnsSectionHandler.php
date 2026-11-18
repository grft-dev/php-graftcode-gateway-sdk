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

final class CommentReturnsSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $returns = $context->parsed['returns'] ?? null;
        if (!is_array($returns)) {
            return null;
        }

        $rawType = (string)($returns['type'] ?? '');
        $description = (string)($returns['description'] ?? '');
        if ($rawType === '' && $description === '') {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_RETURNS,
            [
                $rawType,
                TypeMapping::phpRawToNormalized($rawType),
                InlineMarkup::PHPDOC->encode($description),
                str_contains($rawType, 'null'),
            ]
        );
    }
}
