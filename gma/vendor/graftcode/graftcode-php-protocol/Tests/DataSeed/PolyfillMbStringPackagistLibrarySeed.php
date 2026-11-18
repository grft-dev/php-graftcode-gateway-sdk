<?php

declare(strict_types=1);

namespace Tests\DataSeed;

use GraftcodeProtocol\Enums\{
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\{
    UnifiedGraftModel,
    UnifiedGraftModelInterface
};

/**
 * Published Packagist identity for Symfony's mbstring polyfill.
 *
 * Use this seed when tests must resolve real Composer / Packagist metadata or
 * hosted graft package paths. The canonical package name is
 * {@see self::PACKAGIST_PACKAGE_NAME} (`symfony/polyfill-mbstring`).
 */
final readonly class PolyfillMbStringPackagistLibrarySeed
{
    public const string PACKAGIST_PACKAGE_NAME = 'symfony/polyfill-mbstring';

    public static function createLibrary(): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::LIBRARY,
            '',
            self::PACKAGIST_PACKAGE_NAME,
            '1.28.0',
            'php',
            '1.0.0',
        );
    }
}
