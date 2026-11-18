<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use function dirname;
use function file_exists;
use function is_dir;
use function is_file;
use function is_readable;
use function json_decode;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function uksort;

final class PackageAutoloader
{
    private const int MAX_COMPOSER_SEARCH_DEPTH = 4;

    /** @var list<callable(string): void> */
    private static array $registered = [];

    public static function registerFromProjectRoot(string $projectRoot): void
    {
        $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (false === is_readable($composerPath)) {
            return;
        }

        $content = file_get_contents($composerPath);
        if (false === $content) {
            return;
        }

        /** @var array<string, mixed>|null $composer */
        $composer = json_decode($content, true);
        if (false === is_array($composer)) {
            return;
        }

        /** @var array<string, string> $psr4 */
        $psr4 = $composer['autoload']['psr-4'] ?? [];
        if ($psr4 === []) {
            return;
        }

        uksort($psr4, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $loader = static function (string $class) use ($projectRoot, $psr4): void {
            foreach ($psr4 as $prefix => $relativeDir) {
                if (false === str_starts_with($class, $prefix)) {
                    continue;
                }

                $relativeClass = substr($class, strlen($prefix));
                $file = $projectRoot
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, rtrim($relativeDir, '/\\'))
                    . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                    . '.php';

                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        };

        spl_autoload_register($loader, prepend: true);
        self::$registered[] = $loader;
    }

    public static function resolveProjectRoot(string $path): ?string
    {
        $root = is_dir($path) ? $path : dirname($path);

        for ($depth = 0; $depth <= self::MAX_COMPOSER_SEARCH_DEPTH; ++$depth) {
            if (file_exists($root . DIRECTORY_SEPARATOR . 'composer.json')) {
                return $root;
            }

            $parent = dirname($root);
            if ($parent === $root) {
                break;
            }

            $root = $parent;
        }

        return null;
    }

    public static function unregisterAll(): void
    {
        foreach (self::$registered as $loader) {
            spl_autoload_unregister($loader);
        }
        self::$registered = [];
    }
}
