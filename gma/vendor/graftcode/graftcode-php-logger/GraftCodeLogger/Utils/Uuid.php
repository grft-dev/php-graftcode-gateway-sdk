<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils;

use Exception;
use RuntimeException;
use Throwable;

final readonly class Uuid
{
    private function __construct(private string $uuid)
    {}

    public static function uuid4(): Uuid
    {
        try {
            $data = self::getRandomBytes();

            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

            return new self($uuid);
        } catch (Throwable $th) {
            throw new RuntimeException('Failed to generate UUID: ' . $th->getMessage(), 0, $th);
        }
    }

    private static function getRandomBytes(): string
    {
        try {
            return random_bytes(16);
        } catch (Exception) {

            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }

            return $data;
        }
    }

    public function toString(): string
    {
        return $this->uuid;
    }
}
