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

final class CommentLinksSectionHandler implements CommentDocSectionHandlerInterface
{
    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $linkNodes = [];
        foreach (($context->parsed['links'] ?? []) as $link) {
            $target = (string)($link['target'] ?? '');
            $label = (string)($link['label'] ?? '');
            $kind = (string)($link['kind'] ?? '');
            if ($target === '' && $label === '' && $kind === '') {
                continue;
            }
            if ($kind === '') {
                $kind = 'see';
            }

            $linkNodes[] = CommentDocUgmFactory::node(
                UnifiedGraftModelType::COMMENT_LINK,
                [$target, $label, $kind]
            );
        }

        if ($linkNodes === []) {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_LINKS_ARRAY,
            $linkNodes
        );
    }
}
