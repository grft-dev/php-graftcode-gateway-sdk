<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;

interface ModuleAnalyzerLocalInterface
{
    /**
     * Analyzes a local PHP module/directory/file and returns the serialized result.
     *
     * @param string $sourcePath Local filesystem path to a PHP directory
     * @param string $typeFilter Comma-separated wildcard patterns for types to analyze
     *                           (e.g., "App\\*, *Service, Test*"). Empty string means analyze all types.
     * @param string $methodFilter Comma-separated wildcard patterns for methods to analyze
     * @return array<int> Serialized byte array
     * @throws Exception
     */
    public function analyzeLocal(string $sourcePath, string $typeFilter = '', string $methodFilter = ''): array;

    /**
     * Analyzes classes already loaded in this process.
     * The source path is the Composer project that contains the first matching class.
     *
     * @return array<int>
     * @throws Exception
     */
    public function analyzeLocalRuntime(string $typeNames, string $methodFilter = ''): array;

    /**
     * Builds a GPGE-compatible dependency tree JSON for a local PHP package input.
     *
     * @throws Exception
     */
    public function getDependencyTreeLocal(string $sourcePath): string;

    /**
     * Builds hosted-library statistics JSON from a pre-analyzed local package.
     *
     * @param array<int> $analyzedPackage Serialized byte array from analyzeLocal()
     * @throws Exception
     */
    public function getHostedLibraryStatisticsLocal(array $analyzedPackage): string;
}
