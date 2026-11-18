<?php

declare(strict_types=1);

namespace Tests\CommentDoc;

use GraftcodeProtocol\CommentDoc\NormalizedType;
use GraftcodeProtocol\CommentDoc\TypeMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class TypeMappingTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string}>
     */
    public static function scalarPhpTypeProvider(): array
    {
        return [
            ['string', 'string'],
            ['int', 'integer'],
            ['float', 'number'],
            ['bool', 'boolean'],
            ['array', 'array'],
            ['mixed', 'unknown'],
            ['void', 'void'],
            ['?string', 'string'],
            ['string|null', 'string'],
            ['null|string', 'string'],
            ['\\DateTimeImmutable', 'datetime'],
            ['\\Ramsey\\Uuid\\UuidInterface', 'string'],
            ['', ''],
        ];
    }

    /**
     * PHPDoc array spellings must normalize to wire value "array", not element type.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function arrayLikePhpDocTypeProvider(): array
    {
        return [
            ['string[]', 'array'],
            ['int[]', 'array'],
            ['?string[]', 'array'],
            ['string[]|null', 'array'],
            ['array<string>', 'array'],
            ['list<int>', 'array'],
            ['\\GrftTestSimpleCar\\SimpleCar[]', 'array'],
        ];
    }

    #[Group('unit')]
    #[DataProvider('scalarPhpTypeProvider')]
    public function testPhpRawToNormalizedScalarTypes(string $raw, string $expected): void
    {
        self::assertSame($expected, TypeMapping::phpRawToNormalized($raw));
    }

    #[Group('unit')]
    #[DataProvider('arrayLikePhpDocTypeProvider')]
    public function testPhpRawToNormalizedArrayLikeDocTypes(string $raw, string $expected): void
    {
        self::assertSame($expected, TypeMapping::phpRawToNormalized($raw));
        self::assertSame(NormalizedType::ARRAY, TypeMapping::resolveNormalizedType($raw));
    }

    #[Group('unit')]
    public function testResolveNormalizedTypeReturnsEnum(): void
    {
        self::assertSame(NormalizedType::INTEGER, TypeMapping::resolveNormalizedType('int'));
        self::assertSame(NormalizedType::STRING, TypeMapping::resolveNormalizedType('\\Ramsey\\Uuid\\UuidInterface'));
        self::assertSame(NormalizedType::ARRAY, TypeMapping::resolveNormalizedType('string[]'));
        self::assertNull(TypeMapping::resolveNormalizedType('not-a-known-type'));
    }

    #[Group('unit')]
    public function testNormalizedToPhpTypeMapsNeutralTypes(): void
    {
        self::assertSame('string', TypeMapping::normalizedToPhpType('str', 'string'));
        self::assertSame('int', TypeMapping::normalizedToPhpType('', 'integer'));
        self::assertSame('void', TypeMapping::normalizedToPhpType('', 'void'));
    }

    #[Group('unit')]
    public function testNormalizedToPhpTypeMapsArrayDocTypes(): void
    {
        self::assertSame('array', TypeMapping::normalizedToPhpType('string[]', 'array'));
        self::assertSame('array', TypeMapping::normalizedToPhpType('int[]', 'array'));
    }

    #[Group('unit')]
    public function testNormalizedToPhpTypeFallsBackToRaw(): void
    {
        self::assertSame('MyDto', TypeMapping::normalizedToPhpType('MyDto', ''));
        self::assertSame('Custom', TypeMapping::normalizedToPhpType('Custom', 'unknown_vocab'));
    }
}
