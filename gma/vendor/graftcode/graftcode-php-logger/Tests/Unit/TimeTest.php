<?php

declare(strict_types=1);

namespace Unit;

use DateTimeImmutable;
use DateTimeZone;
use GraftCodeLogger\Utils\Time;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class TimeTest extends TestCase
{
    #[Group('unit')]
    public function testFormattedStringIsUtcWithMicroseconds(): void
    {
        $value = Time::getFormattedString();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $value
        );

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        $this->assertInstanceOf(DateTimeImmutable::class, $parsed);
        $this->assertLessThan(2, abs(time() - $parsed->getTimestamp()));
    }
}
