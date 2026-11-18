<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\Sections;

use GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\{
    CommentDocBuildContext,
    CommentDocSectionHandlerInterface,
    CommentDocUgmFactory
};
use GraftcodeProtocol\CommentDoc\{
    ExceptionMapping,
    InlineMarkup
};
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;

final class CommentThrowsSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $throwNodes = [];
        foreach (($context->parsed['throws'] ?? []) as $throw) {
            $rawType = (string)($throw['type'] ?? '');
            $description = (string)($throw['description'] ?? '');
            if ($rawType === '' && $description === '') {
                continue;
            }

            $throwNodes[] = CommentDocUgmFactory::node(
                UnifiedGraftModelType::COMMENT_THROW,
                [
                    $rawType,
                    ExceptionMapping::fromRaw($rawType)?->value ?? '',
                    InlineMarkup::PHPDOC->encode($description),
                ]
            );
        }

        if ($throwNodes === []) {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_THROWS_ARRAY,
            $throwNodes
        );
    }
}
