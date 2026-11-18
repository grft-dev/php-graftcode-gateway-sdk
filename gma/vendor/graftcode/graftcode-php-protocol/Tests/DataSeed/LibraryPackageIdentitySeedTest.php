<?php

declare(strict_types=1);

namespace Tests\DataSeed;

use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class LibraryPackageIdentitySeedTest extends TestCase
{
    #[Group('unit')]
    public function testPackagistSeedUsesPublishedSymfonyPackageName(): void
    {
        $library = PolyfillMbStringPackagistLibrarySeed::createLibrary();

        self::assertSame(UnifiedGraftModelType::LIBRARY, $library->getUnifiedGraftModelType());
        self::assertSame(
            'symfony/polyfill-mbstring',
            $library->getPayloadByIndex(1),
        );
        self::assertSame(
            PolyfillMbStringPackagistLibrarySeed::PACKAGIST_PACKAGE_NAME,
            $library->getPayloadByIndex(1),
        );
        self::assertNotSame(
            'symfony/polyfill-mb-string',
            $library->getPayloadByIndex(1),
        );
    }

    #[Group('unit')]
    public function testSyntheticNormalizationSeedIsNotPublishedPackagistIdentity(): void
    {
        $library = SyntheticPolyfillMbStringNormalizationLibrarySeed::createLibrary();

        self::assertSame(UnifiedGraftModelType::LIBRARY, $library->getUnifiedGraftModelType());
        self::assertSame(
            'graftcode-test-fixture/polyfill-mb-string',
            $library->getPayloadByIndex(1),
        );
        self::assertStringEndsWith(
            'polyfill-mb-string',
            (string) $library->getPayloadByIndex(1),
        );
        self::assertNotSame(
            PolyfillMbStringPackagistLibrarySeed::PACKAGIST_PACKAGE_NAME,
            $library->getPayloadByIndex(1),
        );
    }
}
