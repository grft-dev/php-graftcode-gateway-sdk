<?php

declare(strict_types=1);

namespace Tests\CommentDoc;

use GraftcodeProtocol\CommentDoc\NormalizedType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class NormalizedTypeTest extends TestCase
{
    #[Group('unit')]
    public function testToPhpTypeMapsWireCasesToIdioms(): void
    {
        self::assertSame('string', NormalizedType::STRING->toPhpType());
        self::assertSame('int', NormalizedType::INTEGER->toPhpType());
        self::assertSame('bool', NormalizedType::BOOLEAN->toPhpType());
        self::assertSame('mixed', NormalizedType::UNKNOWN->toPhpType());
        self::assertSame('\\DateTimeInterface', NormalizedType::DATETIME->toPhpType());
        self::assertSame('\\Throwable', NormalizedType::ERROR->toPhpType());
    }

    #[Group('unit')]
    public function testNormalizedToPhpTypeDelegatesFromWireString(): void
    {
        self::assertSame('int', NormalizedType::normalizedToPhpType('Integer', 'integer'));
        self::assertSame('MyDto', NormalizedType::normalizedToPhpType('MyDto', ''));
    }
}
