<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use GraftcodeModuleAnalyzer\Core\Handler\LibraryMetadata;
use GraftcodeModuleAnalyzer\Utils\Const\Versions;
use GraftcodeModuleAnalyzer\Utils\Exception\MissingComposerVersionException;

use function basename;
use function explode;
use function file_get_contents;
use function is_array;
use function is_readable;
use function json_decode;

final class ComposerLibraryMetadataFactory
{
    /**
     * @throws MissingComposerVersionException
     */
    public static function fromProjectRoot(
        string $projectRoot,
        string $fallbackNamePath,
        ?string $fallbackVersion = null
    ): ?LibraryMetadata {
        $composerMetadata = self::readComposerJson($projectRoot);
        if ($composerMetadata === null) {
            return null;
        }

        return new LibraryMetadata(
            namespace: self::getNamespace($composerMetadata),
            name: self::getName($composerMetadata, $fallbackNamePath),
            version: self::resolveVersion($composerMetadata, $fallbackVersion),
            runtimeName: 'PHP,Version=v' . PHP_VERSION,
            gmaUgmVersion: Versions::getGmaUgmVersion(),
            composerProjectRoot: $projectRoot,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function readComposerJson(string $directory): ?array
    {
        $composerPath = $directory . DIRECTORY_SEPARATOR . 'composer.json';
        if (false === is_readable($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);
        if (false === $content) {
            return null;
        }

        $composerJsonData = json_decode($content, true);

        return is_array($composerJsonData) ? $composerJsonData : null;
    }

    /**
     * @param array<string, mixed> $composerMetadata
     */
    private static function getNamespace(array $composerMetadata): string
    {
        if (empty($composerMetadata['name']) || false === is_string($composerMetadata['name'])) {
            return '';
        }

        $name = explode('/', $composerMetadata['name']);

        return $name[0] ?? '';
    }

    /**
     * @param array<string, mixed> $composerMetadata
     */
    private static function getName(array $composerMetadata, string $originalPath): string
    {
        if (empty($composerMetadata['name']) || false === is_string($composerMetadata['name'])) {
            return basename($originalPath);
        }

        $name = explode('/', $composerMetadata['name']);

        return $name[1] ?? basename($originalPath);
    }

    /**
     * @param array<string, mixed> $composerMetadata
     * @throws MissingComposerVersionException
     */
    private static function resolveVersion(array $composerMetadata, ?string $fallbackVersion): string
    {
        if (false === empty($composerMetadata['version']) && is_string($composerMetadata['version'])) {
            return $composerMetadata['version'];
        }

        if ($fallbackVersion !== null && $fallbackVersion !== '') {
            return $fallbackVersion;
        }

        throw new MissingComposerVersionException();
    }
}
