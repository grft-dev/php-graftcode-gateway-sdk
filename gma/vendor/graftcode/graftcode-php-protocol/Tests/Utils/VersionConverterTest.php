<?php

declare(strict_types=1);

namespace Tests\Utils;

use GraftcodeProtocol\Enums\PackageManager;
use GraftcodeProtocol\Models\PackageVersion;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use InvalidArgumentException;
use GraftcodeProtocol\Versioning\{
    VersionParser,
    VersionConverter,
    VersionValidator
};

final class VersionConverterTest extends TestCase
{
    // PackageVersion Tests
    #[Group('unit')]
    public function testPackageVersionConstructor(): void
    {
        $version = new PackageVersion(1, 2, 3);
        $this->assertEquals(1, $version->major);
        $this->assertEquals(2, $version->minor);
        $this->assertEquals(3, $version->patch);
        $this->assertNull($version->prereleaseLabel);
    }

    #[Group('unit')]
    public function testPackageVersionToString(): void
    {
        $version = new PackageVersion(1, 2, 3);
        $this->assertEquals('1.2.3', (string) $version);

        $version = new PackageVersion(1, 2, 3, 'beta', 1);
        $this->assertEquals('1.2.3-beta.1', (string) $version);
    }

    #[Group('unit')]
    public function testPackageVersionIsPrerelease(): void
    {
        $stable = new PackageVersion(1, 2, 3);
        $this->assertFalse($stable->isPrerelease());
        $this->assertTrue($stable->isStable());

        $prerelease = new PackageVersion(1, 2, 3, 'beta');
        $this->assertTrue($prerelease->isPrerelease());
        $this->assertFalse($prerelease->isStable());
    }

    #[Group('unit')]
    // VersionParser Tests
    public function testParserStableVersion(): void
    {
        $version = VersionParser::parse('1.2.3');
        $this->assertEquals(1, $version->major);
        $this->assertEquals(2, $version->minor);
        $this->assertEquals(3, $version->patch);
        $this->assertNull($version->prereleaseLabel);
    }

    #[Group('unit')]
    public function testParserPrereleaseVersion(): void
    {
        $version = VersionParser::parse('1.2.3-beta.1');
        $this->assertEquals('beta', $version->prereleaseLabel);
        $this->assertEquals(1, $version->prereleaseNumber);
    }

    #[Group('unit')]
    public function testParserCaseInsensitive(): void
    {
        $version = VersionParser::parse('1.0.0-RC.1');
        $this->assertEquals('RC', $version->prereleaseLabel);
        $this->assertEquals(1, $version->prereleaseNumber);
    }

    #[Group('unit')]
    public function testParserInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VersionParser::parse('invalid');
    }

    #[Group('unit')]
    public function testParserTryParse(): void
    {
        [$version, $error] = VersionParser::tryParse('1.2.3');
        $this->assertNotNull($version);
        $this->assertNull($error);

        [$version, $error] = VersionParser::tryParse('invalid');
        $this->assertNull($version);
        $this->assertNotNull($error);
    }

    #[Group('unit')]
    // VersionValidator Tests
    public function testValidatorValidVersions(): void
    {
        $this->assertTrue(VersionValidator::isValid('1.2.3'));
        $this->assertTrue(VersionValidator::isValid('1.2.3-beta.1'));
        $this->assertTrue(VersionValidator::isValid('1.0.0-RC.1'));
    }

    #[Group('unit')]
    public function testValidatorInvalidVersions(): void
    {
        $this->assertFalse(VersionValidator::isValid('invalid'));
        $this->assertFalse(VersionValidator::isValid(''));
        $this->assertFalse(VersionValidator::isValid('1.0'));
    }

    #[Group('unit')]
    public function testValidatorValidate(): void
    {
        $result = VersionValidator::validate('1.2.3');
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getErrorMessage());

        $result = VersionValidator::validate('invalid');
        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getErrorMessage());
    }

    #[Group('unit')]
    // VersionConverter Tests
    public function testConverterToCanonical(): void
    {
        $canonical = VersionConverter::toCanonical('1.2.3');
        $this->assertEquals(1, $canonical->major);

        $canonical = VersionConverter::toCanonical('1.2.3-beta.1');
        $this->assertEquals('beta', $canonical->prereleaseLabel);
    }

    #[Group('unit')]
    public function testConverterFromCanonical(): void
    {
        $canonical = new PackageVersion(1, 2, 3);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.2.3', $composerVersion);

        $canonical = new PackageVersion(1, 2, 3, 'beta', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.2.3-beta.1', $composerVersion);
    }

    #[Group('unit')]
    public function testConverterFromCanonicalNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VersionConverter::fromCanonical(null);
    }

    #[Group('unit')]
    public function testConverterRoundTrip(): void
    {
        $versions = [
            '1.2.3',
            '1.2.3-beta.1',
            '1.0.0-alpha',
            '2.0.0-rc.1',
        ];

        foreach ($versions as $original) {
            $canonical = VersionConverter::toCanonical($original);
            $result = VersionConverter::fromCanonical($canonical);
            $this->assertEquals($original, $result);
        }
    }

    #[Group('unit')]
    public function testRealWorldVersions(): void
    {
        $versions = [
            '1.0.0-alpha',
            '1.0.0-beta.1',
            '1.0.0-rc.1',
            '0.4.0-beta.1',
        ];

        foreach ($versions as $version) {
            $canonical = VersionConverter::toCanonical($version);
            $result = VersionConverter::fromCanonical($canonical);
            $this->assertEquals($version, $result);
        }
    }

    #[Group('unit')]
    public function testPreservesOriginalFormat(): void
    {
        $original = '1.0.0-beta.1';
        $canonical = VersionConverter::toCanonical($original);
        $this->assertEquals('beta', $canonical->prereleaseLabel);
        
        $result = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-beta.1', $result);
    }

    #[Group('unit')]
    // Cross-Platform Conversion Tests
    public function testCrossPlatformPyPiToComposer(): void
    {
        // PyPI: 1.0.0a1 (no hyphen, no dot)
        // -> Canonical: major=1, minor=0, patch=0, label="a", number=1
        // -> Composer: 1.0.0-a.1
        
        $canonical = new PackageVersion(1, 0, 0, 'a', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('1.0.0-a.1', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
    }

    #[Group('unit')]
    public function testCrossPlatformPyPiBetaToComposer(): void
    {
        // PyPI: 1.0.0b1 -> Composer: 1.0.0-b.1
        $canonical = new PackageVersion(1, 0, 0, 'b', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('1.0.0-b.1', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
    }

    #[Group('unit')]
    public function testCrossPlatformRubyToComposer(): void
    {
        // Ruby: 1.0.0.beta.1 (uses dots)
        // -> Canonical: label="beta", number=1
        // -> Composer: 1.0.0-beta.1 (uses hyphens)
        
        $canonical = new PackageVersion(1, 0, 0, 'beta', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('1.0.0-beta.1', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
    }

    #[Group('unit')]
    public function testCrossPlatformMavenSnapshotToComposer(): void
    {
        // Maven: 1.0.0-SNAPSHOT (uppercase)
        // -> Canonical: label="snapshot" (normalized to lowercase)
        // -> Composer: 1.0.0-snapshot
        
        $canonical = new PackageVersion(1, 0, 0, 'snapshot');
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('1.0.0-snapshot', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
    }

    #[Group('unit')]
    public function testCrossPlatformNpmToComposer(): void
    {
        // npm: v1.0.0-beta.1 -> Composer: 1.0.0-beta.1
        $canonical = new PackageVersion(1, 0, 0, 'beta', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('1.0.0-beta.1', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
    }

    #[Group('unit')]
    public function testCrossPlatformMultipleFormatsSameOutput(): void
    {
        // All platforms representing "1.0.0 beta version 1"
        $pypiCanonical = new PackageVersion(1, 0, 0, 'b', 1);
        $rubyCanonical = new PackageVersion(1, 0, 0, 'b', 1);
        $nugetCanonical = new PackageVersion(1, 0, 0, 'b', 1);
        $npmCanonical = new PackageVersion(1, 0, 0, 'b', 1);
        
        $composerFromPypi = VersionConverter::fromCanonical($pypiCanonical);
        $composerFromRuby = VersionConverter::fromCanonical($rubyCanonical);
        $composerFromNuget = VersionConverter::fromCanonical($nugetCanonical);
        $composerFromNpm = VersionConverter::fromCanonical($npmCanonical);
        
        $this->assertEquals('1.0.0-b.1', $composerFromPypi);
        $this->assertEquals('1.0.0-b.1', $composerFromRuby);
        $this->assertEquals('1.0.0-b.1', $composerFromNuget);
        $this->assertEquals('1.0.0-b.1', $composerFromNpm);
    }

    #[Group('unit')]
    public function testCrossPlatformRealWorldScenarioPyPiToComposer(): void
    {
        // Real-world: PyPI "0.4.0b1" converting to Composer
        $canonical = new PackageVersion(0, 4, 0, 'b', 1);
        $composerVersion = VersionConverter::fromCanonical($canonical);
        
        $this->assertEquals('0.4.0-b.1', $composerVersion);
        $this->assertTrue(VersionValidator::isValid($composerVersion));
        
        // PyPI format is invalid for Composer
        $this->assertFalse(VersionValidator::isValid('0.4.0b1'));
        
        // Converted format is valid
        $this->assertTrue(VersionValidator::isValid('0.4.0-b.1'));
    }

    // =====================================================
    // Real Cross-Platform Conversion Tests (String Parsing)
    // =====================================================

    #[Group('unit')]
    public function testRealCrossPlatformPythonToComposer(): void
    {
        // Parse PyPI format "1.0.0a1" using Python converter
        $canonical = VersionConverter::toCanonical('1.0.0a1', PackageManager::PYTHON);

        $this->assertEquals(1, $canonical->major);
        $this->assertEquals(0, $canonical->minor);
        $this->assertEquals(0, $canonical->patch);
        $this->assertEquals('a', $canonical->prereleaseLabel);
        $this->assertEquals(1, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-a.1', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformPythonBetaToComposer(): void
    {
        // Parse PyPI format "2.1.0b3" using Python converter
        $canonical = VersionConverter::toCanonical('2.1.0b3', PackageManager::PYTHON);

        $this->assertEquals('b', $canonical->prereleaseLabel);
        $this->assertEquals(3, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('2.1.0-b.3', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformPythonRcToComposer(): void
    {
        // Parse PyPI format "1.0.0rc2" using Python converter
        $canonical = VersionConverter::toCanonical('1.0.0rc2', PackageManager::PYTHON);

        $this->assertEquals('rc', $canonical->prereleaseLabel);
        $this->assertEquals(2, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-rc.2', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformPythonDevToComposer(): void
    {
        // Parse PyPI format "1.0.0.dev1" using Python converter
        $canonical = VersionConverter::toCanonical('1.0.0.dev1', PackageManager::PYTHON);

        $this->assertEquals('dev', $canonical->prereleaseLabel);
        $this->assertEquals(1, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-dev.1', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRubyGemsToComposer(): void
    {
        // Parse RubyGems format "1.0.0.beta.1" using RubyGems converter
        $canonical = VersionConverter::toCanonical('1.0.0.beta.1', PackageManager::RUBYGEMS);

        $this->assertEquals(1, $canonical->major);
        $this->assertEquals(0, $canonical->minor);
        $this->assertEquals(0, $canonical->patch);
        $this->assertEquals('beta', $canonical->prereleaseLabel);
        $this->assertEquals(1, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-beta.1', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRubyGemsAlphaToComposer(): void
    {
        // Parse RubyGems format "2.3.0.alpha.5" using RubyGems converter
        $canonical = VersionConverter::toCanonical('2.3.0.alpha.5', PackageManager::RUBYGEMS);

        $this->assertEquals('alpha', $canonical->prereleaseLabel);
        $this->assertEquals(5, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('2.3.0-alpha.5', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformMavenToComposer(): void
    {
        // Parse Maven format "1.0.0-SNAPSHOT" using Maven converter
        $canonical = VersionConverter::toCanonical('1.0.0-SNAPSHOT', PackageManager::MAVEN);

        $this->assertEquals(1, $canonical->major);
        $this->assertEquals(0, $canonical->minor);
        $this->assertEquals(0, $canonical->patch);
        $this->assertEquals('snapshot', $canonical->prereleaseLabel);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-snapshot', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformMavenAlphaToComposer(): void
    {
        // Parse Maven format "1.0.0-alpha-1" using Maven converter
        $canonical = VersionConverter::toCanonical('1.0.0-alpha-1', PackageManager::MAVEN);
        $this->assertEquals('alpha', $canonical->prereleaseLabel);
        $this->assertEquals(1, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-alpha.1', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformNpmToComposer(): void
    {
        // Parse npm format "1.0.0-beta.1" using npm converter (same as NuGet/SemVer)
        $canonical = VersionConverter::toCanonical('1.0.0-beta.1', PackageManager::NPM);

        $this->assertEquals(1, $canonical->major);
        $this->assertEquals(0, $canonical->minor);
        $this->assertEquals(0, $canonical->patch);
        $this->assertEquals('beta', $canonical->prereleaseLabel);
        $this->assertEquals(1, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-beta.1', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformNuGetToComposer(): void
    {
        // Parse NuGet format "1.0.0-rc.2" using NuGet converter
        $canonical = VersionConverter::toCanonical('1.0.0-rc.2', PackageManager::NUGET);

        $this->assertEquals('rc', $canonical->prereleaseLabel);
        $this->assertEquals(2, $canonical->prereleaseNumber);

        // Convert to Composer format
        $composerVersion = VersionConverter::fromCanonical($canonical);
        $this->assertEquals('1.0.0-rc.2', $composerVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformPythonToMaven(): void
    {
        // Parse PyPI format and convert to Maven format
        $canonical = VersionConverter::toCanonical('1.0.0a1', PackageManager::PYTHON);

        // Convert to Maven format
        $mavenVersion = VersionConverter::fromCanonical($canonical, PackageManager::MAVEN);
        $this->assertEquals('1.0.0-a-1', $mavenVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformMavenToRubyGems(): void
    {
        // Parse Maven format and convert to RubyGems format
        $canonical = VersionConverter::toCanonical('1.0.0-beta-2', PackageManager::MAVEN);

        // Convert to RubyGems format
        $rubyVersion = VersionConverter::fromCanonical($canonical, PackageManager::RUBYGEMS);
        $this->assertEquals('1.0.0.beta.2', $rubyVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRubyGemsToPython(): void
    {
        // Parse RubyGems format and convert to Python format
        $canonical = VersionConverter::toCanonical('1.0.0.rc.3', PackageManager::RUBYGEMS);

        // Convert to Python format
        $pythonVersion = VersionConverter::fromCanonical($canonical, PackageManager::PYTHON);
        $this->assertEquals('1.0.0rc3', $pythonVersion);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRoundTripPython(): void
    {
        // Round-trip: Python -> Canonical -> Python
        $original = '1.0.0b2';
        $canonical = VersionConverter::toCanonical($original, PackageManager::PYTHON);
        $result = VersionConverter::fromCanonical($canonical, PackageManager::PYTHON);

        $this->assertEquals($original, $result);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRoundTripRubyGems(): void
    {
        // Round-trip: RubyGems -> Canonical -> RubyGems
        $original = '1.0.0.alpha.1';
        $canonical = VersionConverter::toCanonical($original, PackageManager::RUBYGEMS);
        $result = VersionConverter::fromCanonical($canonical, PackageManager::RUBYGEMS);

        $this->assertEquals($original, $result);
    }

    #[Group('unit')]
    public function testRealCrossPlatformRoundTripMaven(): void
    {
        // Round-trip: Maven -> Canonical -> Maven
        $original = '1.0.0-rc-1';
        $canonical = VersionConverter::toCanonical($original, PackageManager::MAVEN);
        $result = VersionConverter::fromCanonical($canonical, PackageManager::MAVEN);

        $this->assertEquals($original, $result);
    }

    #[Group('unit')]
    public function testRealCrossPlatformAllToComposer(): void
    {
        // All platforms representing same logical version "1.0.0 beta 1" -> Composer
        $testCases = [
            ['1.0.0b1', PackageManager::PYTHON, '1.0.0-b.1'],
            ['1.0.0.beta.1', PackageManager::RUBYGEMS, '1.0.0-beta.1'],
            ['1.0.0-beta-1', PackageManager::MAVEN, '1.0.0-beta.1'],
            ['1.0.0-beta.1', PackageManager::NPM, '1.0.0-beta.1'],
            ['1.0.0-beta.1', PackageManager::NUGET, '1.0.0-beta.1'],
        ];

        foreach ($testCases as [$sourceVersion, $sourceManager, $expectedComposer]) {
            $canonical = VersionConverter::toCanonical($sourceVersion, $sourceManager);
            $composerVersion = VersionConverter::fromCanonical($canonical);

            $this->assertSame($expectedComposer, $composerVersion);
        }
    }

    #[Group('unit')]
    public function testRealCrossPlatformStableVersions(): void
    {
        // Stable versions should work across all platforms
        $managers = [
            PackageManager::PYTHON,
            PackageManager::RUBYGEMS,
            PackageManager::MAVEN,
            PackageManager::NPM,
            PackageManager::NUGET,
            PackageManager::CANONICAL,
        ];

        foreach ($managers as $manager) {
            $canonical = VersionConverter::toCanonical('1.2.3', $manager);

            $this->assertEquals(1, $canonical->major);
            $this->assertEquals(2, $canonical->minor);
            $this->assertEquals(3, $canonical->patch);
            $this->assertNull($canonical->prereleaseLabel);
            $this->assertNull($canonical->buildMetadata);
            $this->assertNull($canonical->epoch);
            $this->assertNull($canonical->localVersion);

            $this->assertNull($canonical->platform);
            $composerVersion = VersionConverter::fromCanonical($canonical);
            $this->assertEquals('1.2.3', $composerVersion);
        }
    }
}
