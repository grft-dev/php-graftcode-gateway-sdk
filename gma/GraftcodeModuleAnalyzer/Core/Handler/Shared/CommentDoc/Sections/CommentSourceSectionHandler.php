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
use Throwable;

final class CommentSourceSectionHandler implements CommentDocSectionHandlerInterface
{
    private const string COMMENT_LANGUAGE = 'php';
    private const string COMMENT_STYLE = 'phpdoc';

    public function buildSection(CommentDocBuildContext $context): ?UnifiedGraftModel
    {
        $sourceRaw = $context->emitCodeExamples ? $context->rawDoc : '';

        $filePath = '';
        try {
            $filePath = $context->reflection->getFileName() ?: '';
        } catch (Throwable) {
            // Reflection occasionally throws on internal functions.
        }

        $lineStart = 0;
        $lineEnd = 0;
        try {
            $lineStart = $context->reflection->getStartLine() ?: 0;
            $lineEnd = $context->reflection->getEndLine() ?: 0;
        } catch (Throwable) {
            // keep zeros
        }

        return CommentDocUgmFactory::node(
            UnifiedGraftModelType::COMMENT_SOURCE,
            [
                self::COMMENT_LANGUAGE,
                CommentDocUgmFactory::normalizeSourceFileName($filePath),
                $lineStart,
                $lineEnd,
                self::COMMENT_STYLE,
                $sourceRaw,
            ]
        );
    }
}
