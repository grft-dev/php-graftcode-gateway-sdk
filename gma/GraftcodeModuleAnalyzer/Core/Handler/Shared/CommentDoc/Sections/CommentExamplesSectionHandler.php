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

final class CommentExamplesSectionHandler implements CommentDocSectionHandlerInterface
{
    private const string COMMENT_LANGUAGE = 'php';

    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        if (!$context->emitCodeExamples) {
            return null;
        }

        $exampleNodes = [];
        foreach (($context->parsed['examples'] ?? []) as $example) {
            $title = (string)($example['title'] ?? '');
            $code = (string)($example['code'] ?? '');
            if ($title === '' && $code === '') {
                continue;
            }

            $exampleNodes[] = CommentDocUgmFactory::node(
                UnifiedGraftModelType::COMMENT_EXAMPLE,
                [$title, self::COMMENT_LANGUAGE, $code]
            );
        }

        if ($exampleNodes === []) {
            return null;
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_EXAMPLES_ARRAY,
            $exampleNodes
        );
    }
}
