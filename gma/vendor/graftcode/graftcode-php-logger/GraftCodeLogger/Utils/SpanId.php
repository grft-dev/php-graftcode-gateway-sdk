<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils;

use Exception;

final readonly class SpanId
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): SpanId
    {
        return new self(self::randomHex8());
    }

    public function toString(): string
    {
        return $this->value;
    }

    private static function randomHex8(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Exception) {
            $hex = '';
            for ($i = 0; $i < 8; $i++) {
                $hex .= sprintf('%02x', mt_rand(0, 255));
            }
            return $hex;
        }
    }
}
