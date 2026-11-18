<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    AnalyzedObjectUtils,
    Const\Types
};
use GraftcodeProtocol\Models\UnifiedGraftModel;
use ReflectionClass;
use Throwable;

final class SharedModuleHashHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Throwable
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            $this->logger->trackVerbose('Trying to get module hash for object: {objectType}', [
                'objectType' => AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
            ]);

            if (!$analyzedObject instanceof ReflectionClass) {
                $this->logger->trackVerbose('Analyzed module hash object is not a ReflectionClass, returning null');

                return Types::NULL;
            }

            $moduleName = $this->determineModuleName($analyzedObject);

            if (null === $moduleName) {
                $this->logger->trackVerbose('Module name is null, returning "null"');

                return Types::NULL;
            }

            // Use crc32 for deterministic hash across platforms (similar to C# GetDeterministicHashCode)
            // crc32 provides better cross-platform consistency than PHP's hash() function
            $hash = crc32($moduleName);

            $this->logger->trackVerbose('Retrieved module hash: {moduleHash} for module: {moduleName}', [
                'moduleHash' => $hash,
                'moduleName' => $moduleName
            ]);

            return (string) $hash;

        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Unexpected error occurred while getting module hash',
                'objectType' => AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
            ]);

            return Types::UNKNOWN;
        }
    }

    private function determineModuleName(ReflectionClass $reflection): ?string
    {
        $namespace = $reflection->getNamespaceName();
        if (!empty($namespace)) {
            $this->logger->trackVerbose('Using namespace as module name: {namespace}', [
                'namespace' => $namespace
            ]);

            return $namespace;
        }

        $filename = $reflection->getFileName();
        if ($filename !== false) {
            $basename = basename($filename, '.php');
            if (
                !str_starts_with($basename, 'temp_')
                && !str_starts_with($basename, 'test_')
                && $basename !== 'index'
                && $basename !== 'autoload'
            ) {
                $this->logger->trackVerbose('Using filename as module name: {filename}', [
                    'filename' => $basename
                ]);

                return $basename;
            }
        }

        $shortName = $reflection->getShortName();
        if (!empty($shortName)) {
            $this->logger->trackVerbose('Using class short name as module name: {shortName}', [
                'shortName' => $shortName
            ]);

            return strtolower($shortName);
        }

        $this->logger->trackVerbose('Could not determine module name, all methods failed');

        return null;
    }
}
