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

final class CommentMetadataSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $author = (string)($context->parsed['author'] ?? '');
        $since = (string)($context->parsed['since'] ?? '');
        $version = (string)($context->parsed['version'] ?? '');
        $license = (string)($context->parsed['license'] ?? '');
        $tags = (string)($context->parsed['tags'] ?? '');

        if ($author === '' && $since === '' && $version === '' && $license === '' && $tags === '') {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_METADATA,
            [$author, $since, $version, $license, $tags]
        );
    }
}
