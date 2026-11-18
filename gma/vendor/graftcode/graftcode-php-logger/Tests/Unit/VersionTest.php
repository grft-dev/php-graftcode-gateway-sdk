<?php

declare(strict_types=1);

namespace Unit;

use GraftCodeLogger\Utils\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    private static function isValidVersion(string $version): bool
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) === 1;
    }

    #[Group('unit')]
    public function testHasAVersionNumber(): void
    {
        $this->assertNotNull(Version::VERSION);
    }


    #[Group('unit')]
    #[DataProvider('validVersionProvider')]
    public function testValidVersionFormat(string $version): void
    {
        $this->assertTrue(
            self::isValidVersion($version),
            "Version '$version' should be valid."
        );
    }

    #[Group('unit')]
    #[DataProvider('invalidVersionProvider')]
    public function testInvalidVersionFormat(string $version): void
    {
        $this->assertFalse(
            self::isValidVersion($version),
            'Version ' . $version . ' should be invalid.'
        );
    }

    public static function validVersionProvider(): array
    {
        return [
            ['1.2.3'],
            ['0.0.0'],
            ['10.20.30'],
            ['123.456.789'],
        ];
    }

    public static function invalidVersionProvider(): array
    {
        return [
            ['1.2'],
            ['1.2.3.4'],
            ['1.2.a'],
            ['1..3'],
            ['.1.2.3'],
            ['1.2.3.'],
            ['1-2-3com.example'],
            ['abc.def.ghi'],
            ['1.2.3-4'],
            ['-1.2.3'],
        ];
    }
}
