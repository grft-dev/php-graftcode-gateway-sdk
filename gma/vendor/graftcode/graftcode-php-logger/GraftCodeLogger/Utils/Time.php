<?php

namespace GraftCodeLogger\Utils;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

final readonly class Time
{
    public static function getFormattedString(): string
    {
        [$fraction, $seconds] = explode(' ', microtime());
        $microseconds = substr($fraction, 2, 6);

        return gmdate('Y-m-d\TH:i:s', (int) $seconds) . '.' . str_pad($microseconds, 6, '0') . 'Z';
    }

    public static function getFormattedStringFromEpoch(float $epochSeconds): string
    {
        $dateTime = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $epochSeconds));
        if ($dateTime === false) {
            $dateTime = new DateTimeImmutable('@' . (int) floor($epochSeconds));
        }

        return $dateTime
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
