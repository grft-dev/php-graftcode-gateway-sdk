<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Facade;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\ModuleAnalyzer;
use GraftcodeModuleAnalyzer\Core\Handler\ModuleAnalyzerLocal;
use GraftcodeModuleAnalyzer\Models\AnalyzeLocalRequest;
use GraftcodeModuleAnalyzer\Models\AnalyzePackageRequest;

/**
 * Graft / gg entry point for PHP module analysis (blob and local workflows).
 * Published UGM surface is this class plus {@see AnalyzePackageRequest} and {@see AnalyzeLocalRequest}.
 */
final readonly class ModuleAnalyzerService
{
    private function __construct()
    {}

    /**
     * @param array<int, string> $dependencies
     *
     * @return array<int>
     * @throws Exception
     */
    public static function analyze(
        string $containerName,
        string $inputBlobName,
        string $traceId = '',
        string $parentTraceId = '',
        array $dependencies = [],
    ): array {
        return new ModuleAnalyzer()->analyze(
            $containerName,
            $inputBlobName,
            $traceId,
            $parentTraceId,
            $dependencies,
        );
    }

    /**
     * @return array<int>
     * @throws Exception
     */
    public static function analyzeWithRequest(AnalyzePackageRequest $request): array
    {
        return self::analyze(
            $request->containerName,
            $request->inputBlobName,
            $request->traceId,
            $request->parentTraceId,
            $request->dependencies,
        );
    }

    /**
     * @return array<int>
     * @throws Exception
     */
    public static function analyzeLocal(
        string $sourcePath,
        string $typeFilter = '',
        string $methodFilter = '',
    ): array {
        return new ModuleAnalyzerLocal()->analyzeLocal($sourcePath, $typeFilter, $methodFilter);
    }

    /**
     * @return array<int>
     * @throws Exception
     */
    public static function analyzeLocalRuntime(string $typeNames, string $methodFilter = ''): array
    {
        return new ModuleAnalyzerLocal()->analyzeLocalRuntime($typeNames, $methodFilter);
    }

    /**
     * @return array<int>
     * @throws Exception
     */
    public static function analyzeLocalWithRequest(AnalyzeLocalRequest $request): array
    {
        return self::analyzeLocal(
            $request->sourcePath,
            $request->typeFilter,
            $request->methodFilter,
        );
    }

    /**
     * @param array<int> $analyzedPackage
     * @throws Exception
     */
    public static function getHostedLibraryStatisticsLocal(array $analyzedPackage): string
    {
        return new ModuleAnalyzerLocal()->getHostedLibraryStatisticsLocal($analyzedPackage);
    }

    /**
     * @throws Exception
     */
    public static function getDependencyTreeLocal(string $sourcePath): string
    {
        return new ModuleAnalyzerLocal()->getDependencyTreeLocal($sourcePath);
    }
}
