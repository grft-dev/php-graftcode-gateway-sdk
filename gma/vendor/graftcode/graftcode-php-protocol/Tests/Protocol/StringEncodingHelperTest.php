<?php

declare(strict_types=1);

namespace Tests\Protocol;

use GraftcodeProtocol\Enums\StringEncodingMode;
use GraftcodeProtocol\Protocol\StringEncodingHelper;
use GraftcodeProtocol\Utils\Exception\ConversionByteToStringException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class StringEncodingHelperTest extends TestCase
{
    /** UTF-8 for U+1F600 (grinning face). */
    private const NON_BMP_UTF8_BYTES = [0xF0, 0x9F, 0x98, 0x80];

    #[Group('unit')]
    public function test_utf8StringToByteArray_roundTripsValidUtf8(): void
    {
        $this->assertSame(
            self::NON_BMP_UTF8_BYTES,
            StringEncodingHelper::utf8StringToByteArray("\u{1F600}")
        );
    }

    #[Group('unit')]
    public function test_utf8StringToByteArray_rejectsInvalidUtf8String(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('String is not valid UTF-8.');

        StringEncodingHelper::utf8StringToByteArray("\xC3\x28");
    }

    #[Group('unit')]
    public function test_decodeWireBytes_utf8_acceptsValidNonBmpCodePoint(): void
    {
        $this->assertSame(
            "\u{1F600}",
            StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, self::NON_BMP_UTF8_BYTES)
        );
    }

    #[Group('unit')]
    public function test_decodeWireBytes_utf16_encodesNonBmpAsSurrogatePair(): void
    {
        $expected = pack('v', 0xD83D) . pack('v', 0xDE00);

        $this->assertSame(
            $expected,
            StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF16, self::NON_BMP_UTF8_BYTES)
        );
    }

    #[Group('unit')]
    public function test_decodeWireBytes_utf32_encodesNonBmpCodePointLittleEndian(): void
    {
        $this->assertSame(
            pack('V', 0x1F600),
            StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF32, self::NON_BMP_UTF8_BYTES)
        );
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsBadContinuationByte(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xC2, 0x41]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsOverlongEncoding(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        // Overlong encoding of U+0000 (NUL).
        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xC0, 0x80]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsSurrogateCodePoint(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        // UTF-8 for U+D800 (surrogate, invalid scalar value).
        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xED, 0xA0, 0x80]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsOutOfRangeCodePoint(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        // UTF-8 for U+110000 (above Unicode maximum).
        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xF4, 0x90, 0x80, 0x80]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsTruncatedSequence(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        // Leading byte for 3-byte sequence without continuations.
        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xE2, 0x82]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_rejectsInvalidLeadingByte(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        StringEncodingHelper::decodeWireBytes(StringEncodingMode::UTF8, [0xFF]);
    }

    #[Group('unit')]
    public function test_decodeWireBytes_ascii_rejectsNonAsciiUtf8Payload(): void
    {
        $this->expectException(ConversionByteToStringException::class);

        // UTF-8 for U+00A3 (pound sign).
        StringEncodingHelper::decodeWireBytes(StringEncodingMode::ASCII, [0xC2, 0xA3]);
    }
}
