<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\Sections;

use GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\{
    CommentDocBuildContext,
    CommentDocSectionHandlerInterface,
    CommentDocUgmFactory
};
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;

final class CommentDeprecatedSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $deprecated = $context->parsed['deprecated'] ?? null;
        if (!is_array($deprecated)) {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_DEPRECATED,
            [
                true,
                (string)($deprecated['message'] ?? ''),
                (string)($deprecated['replacement'] ?? ''),
            ]
        );
    }
}
