<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Analysis;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\LibraryMetadata;
use GraftcodeProtocol\Models\UnifiedGraftModelInterface;

interface AnalysisEngineInterface
{
    /**
     * @throws Exception
     */
    public function createBaseStructuredCommand(LibraryMetadata $metadata): UnifiedGraftModelInterface;

    /**
     * @throws Exception
     */
    public function performAnalysis(
        string $localInputPath,
        UnifiedGraftModelInterface $structuredCommand,
        string $typeFilter = '',
        string $methodFilter = '',
        ?string $composerProjectRoot = null,
    ): UnifiedGraftModelInterface;

    /**
     * @return array<int>
     * @throws Exception
     */
    public function serializeResult(UnifiedGraftModelInterface $structuredCommand): array;

    public function isZipFile(string $filePath): bool;

    /**
     * @throws Exception
     */
    public function extractZipFile(string $zipPath): string;

    public function resolveProjectRoot(string $extractPath): string;

    public function cleanupDirectory(string $directory): void;
}
