<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\LoggerClientInterface;
use GraftcodeModuleAnalyzer\Core\Analysis\{
    AnalysisEngine,
    AnalysisEngineInterface
};
use GraftcodeModuleAnalyzer\Utils\{
    ComposerLibraryMetadataFactory,
    Const\Chars,
    Const\SpecialChars,
    Const\Versions,
    Enum\OperationOutcome,
    Exception\AnalysisFailedException,
    Exception\File\DependencyCopyFailedException,
    Exception\File\LocalDependencyFileNotFoundException,
    Exception\File\LocalFileNotFoundException,
    Exception\Zip\ZipFileNotFoundInPathException,
    Logger\LoggerManager,
    Storage\StorageManger
};
use GraftcodeStorage\Storage\StorageInterface;
use GraftcodeStorage\Utils\Enum\StorageType;
use Throwable;
use function basename;
use function copy;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function pathinfo;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtoupper;
use function sys_get_temp_dir;

final readonly class ModuleAnalyzer implements ModuleAnalyzerInterface
{
    private LoggerClientInterface $loggerClient;
    private LoggerInterface $logger;
    private StorageInterface $storage;
    private AnalysisEngineInterface $analysisEngine;
    private string $localTempDir;

    /**
     * @throws Throwable
     */
    public function __construct(
    ) {
        $this->loggerClient = LoggerManager::getClient();
        $this->logger = $this->loggerClient->getLogger();
        $this->storage = new StorageManger()->storage;
        $this->analysisEngine = new AnalysisEngine();
        $this->localTempDir = sys_get_temp_dir();

        if (false === is_dir($this->localTempDir)) {
            mkdir($this->localTempDir, 0755, true);
        }

        $this->logger->trackInfo('PHP ModuleAnalyzer initialized');
    }

    /**
     * @throws Exception
     */
    public function analyze(
        string $containerName,
        string $inputBlobName,
        string $traceId = CHARS::EMPTY,
        string $parentTraceId = CHARS::EMPTY,
        array $dependencies = [],
    ): array {
        $operation = $this->loggerClient->startOperation($traceId, $parentTraceId, __FUNCTION__);
        $outcome = OperationOutcome::Success;

        try {
            $this->logger->trackInfo(
                sprintf(
                    'Starting PHP module analysis for container=%s, blob=%s',
                    $containerName,
                    $inputBlobName,
                ),
                [
                    'containerName' => $containerName,
                    'inputBlobName' => $inputBlobName,
                ],
            );

            $localInputPath = $this->prepareInputFile($containerName, $inputBlobName);

            if (false === empty($dependencies)) {
                $this->downloadAndPrepareDependencies($dependencies, $localInputPath);
            }

            $metadata = $this->buildLibraryMetadata($localInputPath, $inputBlobName);
            $structuredCommand = $this->analysisEngine->createBaseStructuredCommand($metadata);
            $structuredCommand = $this->analysisEngine->performAnalysis($localInputPath, $structuredCommand);

            return $this->analysisEngine->serializeResult($structuredCommand);
        } catch (Exception $e) {
            $outcome = OperationOutcome::Failure;
            $this->logger->trackException($e, [
                'message' => 'Analyze failed: {error}',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $outcome = OperationOutcome::Failure;
            $this->logger->trackException($e, [
                'message' => 'Unexpected error during analysis: {error}',
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisFailedException($e->getMessage(), $e);
        } finally {
            $this->analysisEngine->cleanupDirectory($this->localTempDir);
            $operation->close($outcome->isSuccess(), $outcome->value);
        }
    }

    private function getLibraryName(string $blobPath, string $localInputPath): string
    {
        if ($this->isLocalFilePath($blobPath)) {
            return $this->getLibraryNameFromLocalDir($localInputPath);
        }

        $segments = explode('/', trim($blobPath, '/'));
        $count = count($segments);

        if ($count < 2) {
            return $this->getLibraryNameFromLocalDir($localInputPath);
        }

        $folderSegment = trim($segments[$count - 2]);
        $parts = explode('-', $folderSegment, 2);
        if (count($parts) >= 2) {
            return $parts[1];
        }

        return $folderSegment;
    }

    private function getLibraryNameFromLocalDir(string $localInputPath): string
    {
        if (is_dir($localInputPath)) {
            return basename($localInputPath);
        }

        return pathinfo(str_replace('\\', '/', $localInputPath), PATHINFO_FILENAME);
    }

    private function isLocalFilePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return true;
        }

        if (str_starts_with($path, '\\\\')) {
            return true;
        }

        return false;
    }

    private function buildLibraryMetadata(string $localInputPath, string $inputBlobName): LibraryMetadata
    {
        $fromComposer = ComposerLibraryMetadataFactory::fromProjectRoot(
            $localInputPath,
            $localInputPath,
            $this->getPackageVersion($inputBlobName)
        );

        if ($fromComposer !== null) {
            return $fromComposer;
        }

        return new LibraryMetadata(
            namespace: $this->getNamespace($inputBlobName),
            name: $this->getLibraryName($inputBlobName, $localInputPath),
            version: $this->getPackageVersion($inputBlobName),
            runtimeName: 'PHP,Version=v' . PHP_VERSION,
            gmaUgmVersion: Versions::getGmaUgmVersion(),
        );
    }

    private function getNamespace(string $blobPath): string
    {
        $segments = explode('/', trim($blobPath, '/'));
        $count = count($segments);

        if ($count < 2) {
            return CHARS::EMPTY;
        }

        $folderSegment = trim($segments[$count - 2]);
        $parts = explode('-', $folderSegment, 2);
        if (count($parts) >= 2) {
            return $parts[0];
        }

        return CHARS::EMPTY;
    }

    private function getPackageVersion(string $blobPath): string
    {
        if ($this->isLocalFilePath($blobPath)) {
            return Versions::PACKAGE_DEFAULT_VERSION;
        }

        $segments = explode('/', trim($blobPath, '/'));
        $count = count($segments);
        if ($count < 2) {
            return Versions::PACKAGE_DEFAULT_VERSION;
        }

        $fileVersionAndExtension = trim($segments[$count - 1]);
        $fileVersion = preg_replace('/\.(tar\.\w+|zip|gz|bz2|tgz|jar|nupkg|whl)$/i', CHARS::EMPTY, $fileVersionAndExtension);
        if (preg_match('/^(\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?)$/', $fileVersion, $matches)) {
            return $matches[1];
        }

        return Versions::PACKAGE_DEFAULT_VERSION;
    }

    /**
     * @throws Exception
     */
    private function prepareInputFile(string $containerName, string $inputBlobName): string
    {
        $storageType = strtoupper($_ENV[StorageType::STORAGE_TYPE->name] ?? StorageType::AZURE->name);
        $isLocalStorage = $storageType === StorageType::LOCAL->name;

        $localInputPath = $this->getLocalInputPath($containerName, $inputBlobName, $isLocalStorage);

        $this->verifyAndDownloadFileIfExist($inputBlobName, $localInputPath, $isLocalStorage);

        if ($this->analysisEngine->isNotZipFile($localInputPath)) {
            throw new ZipFileNotFoundInPathException($localInputPath);
        }

        $extractedPath = $this->analysisEngine->extractZipFile($localInputPath);
        $projectRoot = $this->analysisEngine->resolveProjectRoot($extractedPath);
        $this->logger->trackInfo('ZIP file processed successfully');

        return $projectRoot;
    }

    private function getLocalInputPath(string $containerName, string $inputBlobName, bool $isLocalStorage): string
    {
        if (false === $isLocalStorage) {
            return $this->localTempDir . DIRECTORY_SEPARATOR . basename($inputBlobName);
        }

        if (str_starts_with($inputBlobName, SpecialChars::SLASH) || str_contains($inputBlobName, SpecialChars::COLON)) {
            $this->logger->trackInfo('Using absolute local path: {path}', ['path' => $inputBlobName]);

            return $inputBlobName;
        }

        $localInputPath = $containerName . DIRECTORY_SEPARATOR . $inputBlobName;
        $this->logger->trackInfo('Local storage detected, using path: {path}', ['path' => $localInputPath]);

        return $localInputPath;
    }

    /**
     * @throws Exception
     */
    private function verifyAndDownloadFileIfExist(
        string $inputBlobName,
        string $localInputPath,
        bool $isLocalStorage,
    ): void {
        if (false === $isLocalStorage) {
            $this->storage->downloadBlob($localInputPath, $inputBlobName);
            $this->logger->trackInfo('File downloaded successfully to {path}', ['path' => $localInputPath]);

            return;
        }

        if (false === file_exists($localInputPath)) {
            throw new LocalFileNotFoundException($localInputPath);
        }
        $this->logger->trackInfo('Using local file: {path}', ['path' => $localInputPath]);
    }

    /**
     * @throws Exception
     */
    private function downloadAndPrepareDependencies(
        array $dependencies,
        string $localPath,
    ): void {
        $dependencyPath = dirname($localPath) . DIRECTORY_SEPARATOR . 'dependencies';

        if (!is_dir($dependencyPath)) {
            mkdir($dependencyPath, 0755, true);
        }

        foreach ($dependencies as $dependency) {
            $localDependencyPath = $dependencyPath . DIRECTORY_SEPARATOR . basename($dependency);

            $this->downloadDependency($dependency, $localDependencyPath);

            $this->logger->trackInfo(
                'Prepared dependency: {dependency} to {path}',
                ['dependency' => $dependency, 'path' => $localDependencyPath],
            );

            if ($this->analysisEngine->isNotZipFile($localDependencyPath)) {
                throw new ZipFileNotFoundInPathException($localDependencyPath);
            }

            $extractedPath = $this->analysisEngine->extractZipFile($localDependencyPath);
            $this->logger->trackInfo('Dependency ZIP extracted to: {path}', ['path' => $extractedPath]);
        }
    }

    /**
     * @throws Exception
     */
    private function downloadDependency(string $dependency, string $localDependencyPath): void
    {
        if ($this->isLocalDependencyPath($dependency)) {
            $this->copyLocalDependency($dependency, $localDependencyPath);

            return;
        }

        $this->storage->downloadBlob($localDependencyPath, $dependency);
    }

    private function isLocalDependencyPath(string $dependency): bool
    {
        return str_contains($dependency, SpecialChars::COLON)
            || str_starts_with($dependency, SpecialChars::SLASH)
            || file_exists($dependency);
    }

    /**
     * @throws Exception
     */
    private function copyLocalDependency(string $sourcePath, string $destinationPath): void
    {
        if (false === file_exists($sourcePath)) {
            throw new LocalDependencyFileNotFoundException($sourcePath);
        }

        if (false === copy($sourcePath, $destinationPath)) {
            throw new DependencyCopyFailedException($sourcePath, $destinationPath);
        }

        $this->logger->trackInfo('Copied local dependency: {source} to {destination}', [
            'source' => $sourcePath,
            'destination' => $destinationPath,
        ]);
    }
}
