<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use Exception;
use GraftCodeLogger\Logger\LoggerInterface;
use GraftCodeLogger\LoggerClientInterface;
use GraftcodeModuleAnalyzer\Core\Analysis\{
    AnalysisEngine,
    AnalysisEngineInterface,
    TypeFilterHelper
};
use GraftcodeModuleAnalyzer\Core\Dependency\LocalDependencyTreeBuilder;
use GraftcodeModuleAnalyzer\Core\Statistics\UgmHostedLibraryStatisticsBuilder;
use GraftcodeModuleAnalyzer\Utils\Enum\OperationOutcome;
use GraftcodeModuleAnalyzer\Utils\{
    ComposerLibraryMetadataFactory,
    PackageAutoloader
};
use GraftcodeModuleAnalyzer\Utils\Exception\{
    CannotLoadComposerJsonException,
    ComposerJsonNotFoundException,
    File\EmptySourceFilePathException,
    File\SourceFilePathNotFoundException,
    File\SourcePackagePathNotDirectoryException,
    LocalAnalysisFailedException,
    MissingComposerVersionException
};
use GraftcodeModuleAnalyzer\Utils\Logger\LoggerManager;
use GraftcodeProtocol\Protocol\Serializers\{
    HostedLibraryStatisticsToJsonSerializer,
    DependencyTreeToJsonSerializer
};
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Throwable;
use function dirname;
use function file_exists;

final readonly class ModuleAnalyzerLocal implements ModuleAnalyzerLocalInterface
{
    private LoggerClientInterface $loggerClient;
    private LoggerInterface $logger;
    private AnalysisEngineInterface $analysisEngine;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->loggerClient = LoggerManager::getClient();
        $this->logger = $this->loggerClient->getLogger();
        $this->analysisEngine = new AnalysisEngine();

        $this->logger->trackInfo('PHP ModuleAnalyzerLocal initialized');
    }

    /**
     * @return array<int>
     * @throws Exception
     */
    public function analyzeLocalRuntime(string $typeNames, string $methodFilter = ''): array
    {
        return $this->analyzeLocal($this->resolveLoadedProjectPath($typeNames), $typeNames, $methodFilter);
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function resolveLoadedProjectPath(string $typeNames): string
    {
        $filter = trim($typeNames);
        if ($filter === '') {
            throw new InvalidArgumentException('At least one type name is required.');
        }

        $firstPattern = $this->firstPattern($filter);
        $wildcard = str_contains($firstPattern, '*') || str_contains($firstPattern, '?');
        $matchFilter = $wildcard ? $filter : $firstPattern;

        foreach ([...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()] as $className) {
            if (!TypeFilterHelper::matchesFilter($className, $matchFilter)) {
                continue;
            }

            $file = new ReflectionClass($className)->getFileName();
            if ($file === false || !is_file($file)) {
                continue;
            }

            return $this->composerProjectRoot($file);
        }

        throw new RuntimeException('Type not found for filter: ' . $filter);
    }

    public function analyzeLocal(string $sourcePath, string $typeFilter = '', string $methodFilter = ''): array
    {
        $operation = $this->loggerClient->startOperation(null, null, __FUNCTION__);
        $outcome = OperationOutcome::Success;

        $this->validateSourcePath($sourcePath);

        $this->logger->trackInfo(
            'Starting local PHP analysis for path={sourcePath}',
            ['sourcePath' => $sourcePath],
        );

        try {
            $this->isDir($sourcePath);

            $workingPath = $this->analysisEngine->resolveProjectRoot($sourcePath);

            $metadata = $this->buildLibraryMetadata($workingPath, $sourcePath);
            $structuredCommand = $this->analysisEngine->createBaseStructuredCommand($metadata);
            $structuredCommand = $this->analysisEngine->performAnalysis(
                $workingPath,
                $structuredCommand,
                $typeFilter,
                $methodFilter,
                $metadata->composerProjectRoot,
            );

            return $this->analysisEngine->serializeResult($structuredCommand);
        } catch (Exception $e) {
            $outcome = OperationOutcome::Failure;
            $this->logger->trackException($e, [
                'message' => 'Local analysis failed: {error}',
                'error' => $e->getMessage(),
                'sourcePath' => $sourcePath,
            ]);

            throw $e;
        } catch (Throwable $e) {
            $outcome = OperationOutcome::Failure;
            $this->logger->trackException($e, [
                'message' => 'Unexpected error during local analysis: {error}',
                'error' => $e->getMessage(),
            ]);

            throw new LocalAnalysisFailedException($e->getMessage(), $e);
        } finally {
            $operation->close($outcome->isSuccess(), $outcome->value);
        }
    }

    /**
     * @throws Exception
     */
    public function getDependencyTreeLocal(string $sourcePath): string
    {
        $this->validateSourcePath($sourcePath);

        $composerProjectRoot = PackageAutoloader::resolveProjectRoot($sourcePath);
        if (null === $composerProjectRoot) {
            throw new ComposerJsonNotFoundException($sourcePath . DIRECTORY_SEPARATOR . 'composer.json');
        }

        $composerPath = $composerProjectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (false === file_exists($composerPath)) {
            throw new ComposerJsonNotFoundException($composerPath);
        }

        $tree = LocalDependencyTreeBuilder::buildFromComposerJson($composerPath);

        return DependencyTreeToJsonSerializer::serialize($tree);
    }

    /**
     * @throws Exception
     */
    public function getHostedLibraryStatisticsLocal(array $analyzedPackage): string
    {
        $statistics = UgmHostedLibraryStatisticsBuilder::build($analyzedPackage);

        return HostedLibraryStatisticsToJsonSerializer::serialize($statistics);
    }

    private function firstPattern(string $filter): string
    {
        foreach (explode(',', $filter) as $candidate) {
            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        throw new InvalidArgumentException('At least one type name is required.');
    }

    private function composerProjectRoot(string $file): string
    {
        return PackageAutoloader::resolveProjectRoot($file) ?? dirname($file);
    }

    private function validateSourcePath(string $sourcePath): void
    {
        if (empty($sourcePath)) {
            throw new EmptySourceFilePathException();
        }

        if (false === file_exists($sourcePath)) {
            throw new SourceFilePathNotFoundException($sourcePath);
        }
    }

    private function isDir(string $sourcePath): void
    {
        if (false === is_dir($sourcePath)) {
            throw new SourcePackagePathNotDirectoryException($sourcePath);
        }
    }

    /**
     * @throws CannotLoadComposerJsonException
     * @throws MissingComposerVersionException
     */
    private function buildLibraryMetadata(
        string $workingPath,
        string $originalPath,
    ): LibraryMetadata
    {
        $composerProjectRoot = PackageAutoloader::resolveProjectRoot($originalPath)
            ?? PackageAutoloader::resolveProjectRoot($workingPath);

        $metadata = ComposerLibraryMetadataFactory::fromProjectRoot(
            $composerProjectRoot ?? $workingPath,
            $originalPath,
        );

        if (null === $metadata) {
            throw new CannotLoadComposerJsonException();
        }

        $this->logger->trackInfo('Read composer.json metadata: {name} {version}', [
            'NameSpace' => $metadata->namespace,
            'Name' => $metadata->name,
            'Version' => $metadata->version,
        ]);

        return $metadata;
    }
}
