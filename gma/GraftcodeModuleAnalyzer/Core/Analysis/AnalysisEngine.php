<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Analysis;

use Exception;
use FilesystemIterator;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftcodeModuleAnalyzer\Utils\{
    Const\Chars,
    Const\FileExtensionName,
    Exception\Zip\ZipFileOpenException
};
use GraftcodeModuleAnalyzer\Core\Handler\{
    Handlers,
    LibraryMetadata
};
use GraftcodeModuleAnalyzer\Utils\{
    ComposerLibraryMetadataFactory,
    InterfaceAnalyzer
};
use GraftcodeModuleAnalyzer\Utils\Logger\LoggerManager;
use ReflectionClass;
use GraftcodeProtocol\Enums\{
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\{
    UnifiedGraftModel,
    UnifiedGraftModelInterface
};
use GraftcodeProtocol\Protocol\Serializers\UnifiedGraftModelSerializer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

use function array_merge;
use function array_shift;
use function count;
use function dirname;
use function fclose;
use function file_exists;
use function fopen;
use function fread;
use function is_dir;
use function mkdir;
use function pathinfo;
use function rmdir;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function unlink;

final readonly class AnalysisEngine implements AnalysisEngineInterface
{
    private LoggerInterface $logger;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->logger = LoggerManager::getLogger();
    }

    public function createBaseStructuredCommand(LibraryMetadata $metadata): UnifiedGraftModelInterface
    {
        $command = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::LIBRARY,
        );

        $command = $command->addArgToPayload($metadata->namespace);
        $command = $command->addArgToPayload($metadata->name);
        $command = $command->addArgToPayload($metadata->version);
        $command = $command->addArgToPayload($metadata->runtimeName);

        return $command->addArgToPayload($metadata->gmaUgmVersion);
    }

    public function performAnalysis(
        string $localInputPath,
        UnifiedGraftModelInterface $structuredCommand,
        string $typeFilter = '',
        string $methodFilter = '',
        ?string $composerProjectRoot = null,
    ): UnifiedGraftModelInterface {
        try {
            $structuredCommand = $this->analyzeClasses(
                $localInputPath,
                $structuredCommand,
                $typeFilter,
                $methodFilter,
                $composerProjectRoot,
            );

            return $this->analyzeGlobalFunctions($localInputPath, $structuredCommand, $methodFilter);
        } catch (Exception $e) {
            $this->logger->trackException($e, [
                'message' => 'Error during analysis: {error}',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Unexpected error during analysis: {error}',
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Unexpected error during analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<int>
     */
    public function serializeResult(UnifiedGraftModelInterface $structuredCommand): array
    {
        return new UnifiedGraftModelSerializer()->serialize($structuredCommand);
    }

    public function isNotZipFile(string $filePath): bool
    {
        return !$this->isZipFile($filePath);
    }

    public function isZipFile(string $filePath): bool
    {
        if (false === file_exists($filePath)) {
            return false;
        }

        $lowercasePath = strtolower($filePath);
        if (str_ends_with($lowercasePath, FileExtensionName::ZIP_WITH_DOT)) {
            return true;
        }

        $handle = @fopen($filePath, 'rb');
        if (false === $handle) {
            return false;
        }

        $header = @fread($handle, 4);
        @fclose($handle);

        if (false === $header || strlen($header) < 4) {
            return false;
        }

        return str_starts_with($header, 'PK');
    }

    public function extractZipFile(string $zipPath): string
    {
        $zip = new ZipArchive();
        if (false === $zip->open($zipPath)) {
            throw new ZipFileOpenException($zipPath);
        }

        $extractPath = dirname($zipPath) . DIRECTORY_SEPARATOR . pathinfo($zipPath, PATHINFO_FILENAME);

        if (false === is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $this->logger->trackInfo('ZIP extracted to: {path}', ['path' => $extractPath]);

        return $extractPath;
    }

    public function resolveProjectRoot(string $extractPath): string
    {
        if (false === is_dir($extractPath)) {
            return $extractPath;
        }

        if (is_file($extractPath . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $extractPath;
        }

        $entries = array_values(array_filter(
            scandir($extractPath) ?: [],
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
        ));

        if (1 !== count($entries)) {
            return $extractPath;
        }

        $singleEntryPath = $extractPath . DIRECTORY_SEPARATOR . $entries[0];
        if (false === is_dir($singleEntryPath)) {
            return $extractPath;
        }

        if (file_exists($singleEntryPath . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $singleEntryPath;
        }

        return $singleEntryPath;
    }

    public function cleanupDirectory(string $directory): void
    {
        if (false === is_dir($directory)) {
            return;
        }

        $this->logger->trackInfo('Cleaning up directory: {dir}', ['dir' => $directory]);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                try {
                    if ($file->isDir()) {
                        rmdir($file->getPathname());
                    } else {
                        unlink($file->getPathname());
                    }
                } catch (Exception $e) {
                    $this->logger->trackWarning('Failed to clean file {path}: {error}', [
                        'path' => $file->getPathname(),
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            rmdir($directory);
            $this->logger->trackInfo('Directory cleaned successfully');
        } catch (Exception $e) {
            $this->logger->trackWarning('Failed to clean directory: {error}', ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->logger->trackWarning('Unexpected error during cleanup: {error}', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @throws Exception
     */
    private function analyzeClasses(
        string $localInputPath,
        UnifiedGraftModelInterface $structuredCommand,
        string $typeFilter,
        string $methodFilter = '',
        ?string $composerProjectRoot = null,
    ): UnifiedGraftModelInterface {
        try {
            $classesToAnalyze = new InterfaceAnalyzer()->returnClassesFromPhp($localInputPath, $composerProjectRoot);

            $handlers = new Handlers();
            $this->configureHandlers($handlers, $localInputPath, $structuredCommand, $composerProjectRoot);
            $handlers->METHOD_FILTER = $methodFilter;
            $handlers->DISCOVERED_TYPE_FQCNS = array_map(
                static fn (ReflectionClass $class): string => $class->getName(),
                $classesToAnalyze,
            );
            $handlers->TYPES_TO_ANALYZE = array_merge($handlers->TYPES_TO_ANALYZE, $classesToAnalyze);

            $this->logger->trackInfo(sprintf('Found %d classes to analyze', count($classesToAnalyze)));

            while (!empty($handlers->TYPES_TO_ANALYZE)) {
                $currentClass = array_shift($handlers->TYPES_TO_ANALYZE);

                if (
                    '' !== $typeFilter
                    && false === TypeFilterHelper::matchesFilter($currentClass->getName(), $typeFilter)
                    && false === TypeFilterHelper::matchesFilter($currentClass->getShortName(), $typeFilter)
                ) {
                    continue;
                }

                try {
                    $typeDefHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::TYPE_DEF->value];
                    $typeModel = $typeDefHandler->generateUnifiedGraftModel(
                        $currentClass,
                        $structuredCommand,
                        $handlers,
                    );

                    $structuredCommand = $structuredCommand->addArgToPayload($typeModel);
                } catch (Exception $e) {
                    $this->logger->trackError('Error analyzing class: {error}', ['error' => $e->getMessage()]);
                    continue;
                }
            }

            return $structuredCommand;
        } catch (Exception $e) {
            $this->logger->trackException($e, [
                'message' => 'Error during class analysis: {error}',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Unexpected error during class analysis: {error}',
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Unexpected error during class analysis: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getAnalysisRootPath(string $localInputPath): string
    {
        $root = realpath($localInputPath) ?: $localInputPath;
        if (is_file($root)) {
            return dirname($root);
        }

        return $root;
    }

    private function configureHandlers(
        Handlers $handlers,
        string $localInputPath,
        UnifiedGraftModelInterface $structuredCommand,
        ?string $composerProjectRoot = null,
    ): void {
        $analysisRoot = $this->getAnalysisRootPath($localInputPath);
        $handlers->ANALYSIS_ROOT_PATH = $analysisRoot;

        $payload = $structuredCommand->getPayload();
        $libraryNamespace = (string) ($payload[0] ?? '');
        $libraryName = (string) ($payload[1] ?? '');
        $libraryVersion = (string) ($payload[2] ?? '');

        $metadata = new LibraryMetadata(
            namespace: $libraryNamespace,
            name: $libraryName,
            version: $libraryVersion,
            runtimeName: (string) ($payload[3] ?? ''),
            gmaUgmVersion: (string) ($payload[4] ?? ''),
        );

        $handlers->LIBRARY_ASSEMBLY_NAME = $metadata->getAssemblyName();
        $handlers->LIBRARY_ASSEMBLY_VERSION = $metadata->version;
        $handlers->LIBRARY_PSR4_ROOTS = $this->resolvePsr4Roots($analysisRoot, $composerProjectRoot);
    }

    /**
     * @return list<string>
     */
    private function resolvePsr4Roots(string $analysisRootPath, ?string $composerProjectRoot = null): array
    {
        $projectRoot = $composerProjectRoot ?? $this->resolveProjectRoot($analysisRootPath);
        $composer = ComposerLibraryMetadataFactory::readComposerJson($projectRoot);
        if ($composer === null || !isset($composer['autoload']['psr-4']) || !is_array($composer['autoload']['psr-4'])) {
            return [];
        }

        $roots = [];
        foreach (array_keys($composer['autoload']['psr-4']) as $prefix) {
            if (!is_string($prefix)) {
                continue;
            }
            $roots[] = rtrim($prefix, '\\');
        }

        return $roots;
    }

    /**
     * @throws Exception
     */
    private function analyzeGlobalFunctions(
        string $localInputPath,
        UnifiedGraftModelInterface $structuredCommand,
        string $methodFilter = '',
    ): UnifiedGraftModelInterface {
        try {
            $globalFunctions = new InterfaceAnalyzer()->returnGlobalFunctionsFromPhp($localInputPath);

            $this->logger->trackInfo(sprintf('Found %d global functions to analyze', count($globalFunctions)));

            $handlers = new Handlers();
            $this->configureHandlers($handlers, $localInputPath, $structuredCommand);

            foreach ($globalFunctions as $functionData) {
                try {
                    $functionName = is_array($functionData) ? (string) ($functionData['name'] ?? CHARS::EMPTY) : CHARS::EMPTY;
                    if ($functionName !== CHARS::EMPTY && false === MethodFilterHelper::matchesFilter($functionName, $methodFilter)) {
                        continue;
                    }

                    $globalMethodHandler = $handlers->ANALYZER_HANDLER[UnifiedGraftModelType::GLOBAL_METHOD->value];
                    $functionModel = $globalMethodHandler->generateUnifiedGraftModel(
                        $functionData,
                        $structuredCommand,
                        $handlers,
                    );

                    $structuredCommand = $structuredCommand->addArgToPayload($functionModel);
                } catch (Exception $e) {
                    $this->logger->trackError('Error analyzing global function: {error}', ['error' => $e->getMessage()]);
                    continue;
                }
            }

            return $structuredCommand;
        } catch (Exception $e) {
            $this->logger->trackException($e, [
                'message' => 'Error during global functions analysis: {error}',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'Unexpected error during global functions analysis: {error}',
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Unexpected error during global functions analysis: ' . $e->getMessage(), 0, $e);
        }
    }
}
