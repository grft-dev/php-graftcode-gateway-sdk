<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Dependency;

use GraftcodeProtocol\Models\DependencyTreeModel;
use InvalidArgumentException;

final class LocalDependencyTreeBuilder
{
    public static function buildFromComposerJson(string $composerJsonPath): DependencyTreeModel
    {
        $content = file_get_contents($composerJsonPath);
        if (false === $content) {
            throw new InvalidArgumentException(sprintf('Unable to read composer.json: %s', $composerJsonPath));
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);
        if (false === is_array($data)) {
            throw new InvalidArgumentException(sprintf('Invalid composer.json: %s', $composerJsonPath));
        }

        $fullName = is_string($data['name'] ?? null) ? $data['name'] : basename(dirname($composerJsonPath));
        $version = is_string($data['version'] ?? null) ? $data['version'] : '0.0.1';
        [$namespace, $name] = self::splitComposerName($fullName);

        $root = self::createNode($namespace, $name, $version);
        self::addComposerDependencies($root, $data['require'] ?? null);

        return $root;
    }

    /**
     * @param DependencyTreeModel $root
     * @param mixed $requirements
     */
    private static function addComposerDependencies(DependencyTreeModel $root, mixed $requirements): void
    {
        if (false === is_array($requirements)) {
            return;
        }

        foreach ($requirements as $packageName => $versionRange) {
            if (false === is_string($packageName) || false === is_string($versionRange)) {
                continue;
            }

            if ($packageName === 'php' || str_starts_with($packageName, 'ext-')) {
                continue;
            }

            if (str_starts_with($packageName, 'graftcode/') || str_starts_with($packageName, 'graft/')) {
                continue;
            }

            [$namespace, $name] = self::splitComposerName($packageName);
            $root->dependencyChildren[] = self::createNode($namespace, $name, self::cleanVersion($versionRange));
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitComposerName(string $fullName): array
    {
        if (!str_contains($fullName, '/')) {
            return ['', $fullName];
        }

        [$namespace, $name] = explode('/', $fullName, 2);

        return [$namespace, $name];
    }

    private static function cleanVersion(string $versionRange): string
    {
        $trimmed = ltrim($versionRange, "^~>=<! ");
        $parts = preg_split('/\s*,\s*/', $trimmed) ?: [];

        return $parts[0] !== '' ? $parts[0] : '0.0.0';
    }

    private static function createNode(string $namespace, string $name, string $version): DependencyTreeModel
    {
        return new DependencyTreeModel(
            dependencyNamespace: $namespace,
            dependencyName: $name,
            dependencyVersion: $version,
        );
    }
}
