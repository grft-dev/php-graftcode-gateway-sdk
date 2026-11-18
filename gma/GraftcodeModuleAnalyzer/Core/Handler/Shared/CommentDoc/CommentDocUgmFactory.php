<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc;

use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;

final class CommentDocUgmFactory
{
    /**
     * @param list<UnifiedGraftModel>|list<mixed> $children
     */
    public static function node(UnifiedGraftModelType $type, array $children): UnifiedGraftModel
    {
        return new UnifiedGraftModel(RuntimeName::PHP, $type, $children);
    }

    public static function normalizeSourceFileName(string $path): string
    {
        // COMMENT_SOURCE payload field "file" (index 1) is the source filename only.
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'classpath:')) {
            $path = substr($path, strlen('classpath:'));
        }

        $normalized = str_replace('\\', '/', $path);
        $basename = basename($normalized);

        return $basename !== '' ? $basename : $path;
    }
}
