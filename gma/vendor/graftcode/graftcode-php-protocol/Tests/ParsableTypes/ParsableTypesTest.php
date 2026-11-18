<?php

declare(strict_types=1);

namespace Tests\ParsableTypes;

use GraftcodeProtocol\ParsableTypes\ParsableTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ParsableTypesTest extends TestCase
{
    /** @var list<string> */
    private const array CANONICAL_PARSABLE_TYPE_NAMES = [
        'Date',
        'System.DateTime',
        'System.DateTimeOffset',
        'URL',
        'java.awt.Color',
        'java.awt.Point',
        'java.net.URI',
        'java.time.Duration',
        'java.time.Instant',
        'java.time.LocalTime',
        'java.time.OffsetDateTime',
        'java.util.UUID',
        'System.Drawing.Color',
        'System.Drawing.Point',
        'datetime.datetime',
        'datetime.timedelta',
        'datetime.time',
        'System.Guid',
        'System.TimeOnly',
        'System.TimeSpan',
        'System.Uri',
        'uuid.UUID',
    ];

    #[Group('unit')]
    public function testParsableTypeNamesAreStableCanonicalStrings(): void
    {
        $this->assertSame(self::CANONICAL_PARSABLE_TYPE_NAMES, ParsableTypes::PARSABLE_TYPE_NAMES);
        $this->assertCount(22, ParsableTypes::PARSABLE_TYPE_NAMES);
    }

    #[Group('unit')]
    #[DataProvider('parsableTypeMembershipProvider')]
    public function testIsParsableTypeRecognizesCanonicalNames(string $foreign, bool $expected): void
    {
        $this->assertSame($expected, ParsableTypes::isParsableType($foreign));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function parsableTypeMembershipProvider(): array
    {
        return [
            'System.DateTime' => ['System.DateTime', true],
            'System.Uri' => ['System.Uri', true],
            'custom dto' => ['com.example.CustomDto', false],
            'blank' => ['', false],
            'whitespace' => ['   ', false],
        ];
    }

    #[Group('unit')]
    #[DataProvider('mapToLocalTypeProvider')]
    public function testMapToLocalTypeMapsAllParsableTypesToPhpEquivalents(
        string $foreign,
        string $expected,
    ): void {
        $this->assertSame($expected, ParsableTypes::mapToLocalType($foreign));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mapToLocalTypeProvider(): array
    {
        return [
            'Date' => ['Date', 'DateTimeImmutable'],
            'System.DateTime' => ['System.DateTime', 'DateTimeImmutable'],
            'java.time.Instant' => ['java.time.Instant', 'DateTimeImmutable'],
            'datetime.datetime' => ['datetime.datetime', 'DateTimeImmutable'],
            'System.DateTimeOffset' => ['System.DateTimeOffset', 'DateTimeImmutable'],
            'java.time.OffsetDateTime' => ['java.time.OffsetDateTime', 'DateTimeImmutable'],
            'java.time.LocalTime' => ['java.time.LocalTime', 'DateTimeImmutable'],
            'System.TimeOnly' => ['System.TimeOnly', 'DateTimeImmutable'],
            'datetime.time' => ['datetime.time', 'DateTimeImmutable'],
            'java.time.Duration' => ['java.time.Duration', 'DateInterval'],
            'System.TimeSpan' => ['System.TimeSpan', 'DateInterval'],
            'datetime.timedelta' => ['datetime.timedelta', 'DateInterval'],
            'java.util.UUID' => ['java.util.UUID', 'HypertubePhpUtils\Uuid'],
            'System.Guid' => ['System.Guid', 'HypertubePhpUtils\Uuid'],
            'uuid.UUID' => ['uuid.UUID', 'HypertubePhpUtils\Uuid'],
            'java.net.URI' => ['java.net.URI', 'HypertubePhpUtils\Uri'],
            'System.Uri' => ['System.Uri', 'HypertubePhpUtils\Uri'],
            'URL' => ['URL', 'HypertubePhpUtils\Uri'],
            'java.awt.Point' => ['java.awt.Point', 'HypertubePhpUtils\Point'],
            'System.Drawing.Point' => ['System.Drawing.Point', 'HypertubePhpUtils\Point'],
            'java.awt.Color' => ['java.awt.Color', 'HypertubePhpUtils\Color'],
            'System.Drawing.Color' => ['System.Drawing.Color', 'HypertubePhpUtils\Color'],
        ];
    }

    #[Group('unit')]
    #[DataProvider('unknownOrBlankForeignTypeProvider')]
    public function testMapToLocalTypeReturnsEmptyForUnknownOrBlank(?string $foreign): void
    {
        $this->assertSame('', ParsableTypes::mapToLocalType($foreign));
        $this->assertFalse(ParsableTypes::isParsableType($foreign));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function unknownOrBlankForeignTypeProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'null' => [null],
            'custom' => ['CustomDto'],
        ];
    }
}
