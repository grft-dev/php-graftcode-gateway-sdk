<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Protocol;

use GraftcodeProtocol\Enums\StringEncodingMode;
use GraftcodeProtocol\Utils\Exception\ConversionByteToStringException;
use InvalidArgumentException;

/**
 * Wire-format string encoding without ext-iconv.
 *
 * Wire payloads are always UTF-8 bytes. {@see decodeWireBytes()} validates UTF-8 and returns a PHP
 * string whose byte representation matches the requested mode (UTF-8/ASCII bytes as-is; UTF-16/UTF-32
 * transcoded to little-endian code-unit bytes).
 *
 * This preserves the previous PHP protocol behavior. It is not equivalent to graftcode-netcore-protocol,
 * which calls Encoding.ASCII/Unicode/UTF32.GetString(bytes) and interprets the payload in the selected
 * encoding directly.
 */
final class StringEncodingHelper
{
    private function __construct()
    {
    }

    /**
     * @return array<int, int>
     */
    public static function utf8StringToByteArray(string $value): array
    {
        if ($value === '') {
            return [];
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('String is not valid UTF-8.');
        }

        $bytes = unpack('C*', $value);

        return $bytes === false ? [] : array_values($bytes);
    }

    /**
     * Wire payload is UTF-8 bytes; decode to PHP string in the requested encoding representation.
     *
     * @param array<int, int> $bytes
     */
    public static function decodeWireBytes(StringEncodingMode $stringEncodingMode, array $bytes): string
    {
        $wireBytes = $bytes === [] ? '' : pack('C*', ...$bytes);

        try {
            return match ($stringEncodingMode) {
                StringEncodingMode::ASCII => self::decodeAscii($wireBytes),
                StringEncodingMode::UTF8 => self::decodeUtf8($wireBytes),
                StringEncodingMode::UTF16 => self::encodeCodePointsToUtf16Le(self::decodeUtf8ToCodePoints($wireBytes)),
                StringEncodingMode::UTF32 => self::encodeCodePointsToUtf32Le(self::decodeUtf8ToCodePoints($wireBytes)),
            };
        } catch (InvalidArgumentException) {
            throw new ConversionByteToStringException($bytes, $stringEncodingMode);
        }
    }

    private static function decodeAscii(string $wireBytes): string
    {
        if ($wireBytes === '') {
            return '';
        }

        self::decodeUtf8ToCodePoints($wireBytes, enforceAscii: true);

        return $wireBytes;
    }

    private static function decodeUtf8(string $wireBytes): string
    {
        if ($wireBytes === '') {
            return '';
        }

        self::decodeUtf8ToCodePoints($wireBytes);

        return $wireBytes;
    }

    /**
     * @return list<int>
     */
    private static function decodeUtf8ToCodePoints(string $bytes, bool $enforceAscii = false): array
    {
        $length = strlen($bytes);
        $codePoints = [];

        for ($index = 0; $index < $length;) {
            $byte = ord($bytes[$index]);

            if ($byte < 0x80) {
                $codePoints[] = $byte;
                $index++;
                continue;
            }

            if (($byte & 0xE0) === 0xC0) {
                $needed = 1;
                $codePoint = $byte & 0x1F;
            } elseif (($byte & 0xF0) === 0xE0) {
                $needed = 2;
                $codePoint = $byte & 0x0F;
            } elseif (($byte & 0xF8) === 0xF0) {
                $needed = 3;
                $codePoint = $byte & 0x07;
            } else {
                throw new InvalidArgumentException('Invalid UTF-8 sequence.');
            }

            if ($index + $needed >= $length) {
                throw new InvalidArgumentException('Invalid UTF-8 sequence.');
            }

            for ($continuation = 1; $continuation <= $needed; $continuation++) {
                $next = ord($bytes[$index + $continuation]);
                if (($next & 0xC0) !== 0x80) {
                    throw new InvalidArgumentException('Invalid UTF-8 sequence.');
                }
                $codePoint = ($codePoint << 6) | ($next & 0x3F);
            }

            if ($needed === 1 && $codePoint < 0x80) {
                throw new InvalidArgumentException('Invalid UTF-8 overlong sequence.');
            }
            if ($needed === 2 && $codePoint < 0x800) {
                throw new InvalidArgumentException('Invalid UTF-8 overlong sequence.');
            }
            if ($needed === 3 && $codePoint < 0x10000) {
                throw new InvalidArgumentException('Invalid UTF-8 overlong sequence.');
            }
            if ($codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
                throw new InvalidArgumentException('Invalid UTF-8 code point.');
            }
            if ($enforceAscii && $codePoint > 0x7F) {
                throw new InvalidArgumentException('ASCII conversion failed.');
            }

            $codePoints[] = $codePoint;
            $index += $needed + 1;
        }

        return $codePoints;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function encodeCodePointsToUtf16Le(array $codePoints): string
    {
        $result = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint <= 0xFFFF) {
                $result .= pack('v', $codePoint);
                continue;
            }

            $adjusted = $codePoint - 0x10000;
            $high = 0xD800 | (($adjusted >> 10) & 0x3FF);
            $low = 0xDC00 | ($adjusted & 0x3FF);
            $result .= pack('v', $high) . pack('v', $low);
        }

        return $result;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function encodeCodePointsToUtf32Le(array $codePoints): string
    {
        $result = '';

        foreach ($codePoints as $codePoint) {
            $result .= pack('V', $codePoint);
        }

        return $result;
    }
}
