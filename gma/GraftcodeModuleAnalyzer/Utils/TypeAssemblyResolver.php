<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use GraftcodeModuleAnalyzer\Core\Handler\Handlers;
use GraftcodeModuleAnalyzer\Utils\Const\GraftCodePrimitiveTypeMapping;
use ReflectionClass;
use ReflectionException;

final class TypeAssemblyResolver
{
    public const string PHP_CORE_ASSEMBLY = 'php.core';

    /**
     * @return array{assemblyName: string, assemblyVersion: string}
     */
    public static function resolve(string $resolvedTypeName, int $primitiveType, Handlers $handlers): array
    {
        if (self::isPhpBuiltin($resolvedTypeName)) {
            return [
                'assemblyName' => self::PHP_CORE_ASSEMBLY,
                'assemblyVersion' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            ];
        }

        if (self::belongsToCurrentLibrary($resolvedTypeName, $handlers)) {
            return [
                'assemblyName' => $handlers->LIBRARY_ASSEMBLY_NAME,
                'assemblyVersion' => $handlers->LIBRARY_ASSEMBLY_VERSION,
            ];
        }

        if (class_exists($resolvedTypeName) || interface_exists($resolvedTypeName) || trait_exists($resolvedTypeName)) {
            try {
                $reflection = new ReflectionClass($resolvedTypeName);
                $fromPath = self::resolveFromFilePath($reflection->getFileName(), $handlers);
                if ($fromPath !== null) {
                    return $fromPath;
                }
            } catch (ReflectionException) {
                // fall through
            }
        }

        return ['assemblyName' => '', 'assemblyVersion' => ''];
    }

    private static function isPhpBuiltin(string $resolvedTypeName): bool
    {
        if (str_contains($resolvedTypeName, '\\')) {
            return false;
        }

        $clean = strtolower(ltrim($resolvedTypeName, '?'));

        return GraftCodePrimitiveTypeMapping::fromString($clean) !== null;
    }

    private static function belongsToCurrentLibrary(string $fqcn, Handlers $handlers): bool
    {
        if ($handlers->LIBRARY_ASSEMBLY_NAME === '') {
            return false;
        }

        if ($handlers->CURRENT_DECLARING_TYPE_FQCN !== '') {
            $declaringNamespace = TypeNameResolver::getNamespaceFromFqcn($handlers->CURRENT_DECLARING_TYPE_FQCN);
            if ($declaringNamespace !== '' && str_starts_with($fqcn, $declaringNamespace . '\\')) {
                return true;
            }
        }

        foreach ($handlers->LIBRARY_PSR4_ROOTS as $root) {
            if ($fqcn === $root || str_starts_with($fqcn, $root . '\\')) {
                return true;
            }
        }

        return in_array($fqcn, $handlers->DISCOVERED_TYPE_FQCNS, true);
    }

    /**
     * @return array{assemblyName: string, assemblyVersion: string}|null
     */
    private static function resolveFromFilePath(string|false $filePath, Handlers $handlers): ?array
    {
        if ($filePath === false) {
            return [
                'assemblyName' => self::PHP_CORE_ASSEMBLY,
                'assemblyVersion' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            ];
        }

        $normalized = str_replace('\\', '/', $filePath);
        $vendorPos = strpos($normalized, '/vendor/');
        if ($vendorPos !== false) {
            $afterVendor = substr($normalized, $vendorPos + strlen('/vendor/'));
            $parts = explode('/', $afterVendor);
            if (count($parts) >= 2) {
                $packageDir = substr($normalized, 0, $vendorPos)
                    . '/vendor/'
                    . $parts[0]
                    . '/'
                    . $parts[1];

                return self::readComposerAssembly(str_replace('/', DIRECTORY_SEPARATOR, $packageDir));
            }
        }

        $root = str_replace('\\', '/', realpath($handlers->ANALYSIS_ROOT_PATH) ?: $handlers->ANALYSIS_ROOT_PATH);
        if ($root !== '' && str_starts_with($normalized, $root)) {
            return [
                'assemblyName' => $handlers->LIBRARY_ASSEMBLY_NAME,
                'assemblyVersion' => $handlers->LIBRARY_ASSEMBLY_VERSION,
            ];
        }

        return null;
    }

    /**
     * @return array{assemblyName: string, assemblyVersion: string}
     */
    private static function readComposerAssembly(string $packageDir): array
    {
        $metadata = ComposerLibraryMetadataFactory::readComposerJson($packageDir);

        if ($metadata === null || empty($metadata['name']) || !is_string($metadata['name'])) {
            return ['assemblyName' => '', 'assemblyVersion' => ''];
        }

        return [
            'assemblyName' => $metadata['name'],
            'assemblyVersion' => is_string($metadata['version'] ?? null) ? $metadata['version'] : '',
        ];
    }
}
