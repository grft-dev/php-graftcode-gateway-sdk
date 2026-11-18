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
 * Synthetic fixture for kebab-case package-name normalization only.
 *
 * {@see self::PACKAGE_NAME} (`graftcode-test-fixture/polyfill-mb-string`) is
 * intentionally NOT a published Packagist package. It exists to exercise
 * tokenization of the `polyfill-mb-string` slug without coupling tests to a
 * fictional Symfony package identity.
 */
final readonly class SyntheticPolyfillMbStringNormalizationLibrarySeed
{
    public const string PACKAGE_NAME = 'graftcode-test-fixture/polyfill-mb-string';

    public static function createLibrary(): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::LIBRARY,
            '',
            self::PACKAGE_NAME,
            '0.0.0',
            'php',
            '1.0.0',
        );
    }
}
