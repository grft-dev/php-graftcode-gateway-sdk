<?php

declare(strict_types=1);

namespace GraftcodeProtocol\CommentDoc;

/**
 * Cross-runtime exception category mapping for `COMMENT_THROW` payloads.
 *
 * The mapping is sourced **verbatim** from the Hypertube SDK -- the
 * same SDK that GMA and GCG already use as their runtime bridge.
 * Re-using Hypertube's `ExceptionType` enum guarantees that whatever
 * category we emit into the UGM here will be re-throwable on the
 * other side of the bridge with zero translation work.
 *
 * Wire contract -- `COMMENT_THROW.payload`:
 *
 *     [raw_type, category, description]
 *
 *   - raw_type    : source-language class name (string), preserved for
 *                   documentation and fallback rendering.
 *   - category    : one of the enum cases' `->value` if the raw type is
 *                   recognized, or null otherwise (callers serialize that
 *                   as '' on the wire to preserve the payload shape).
 *   - description : free-form text (neutral markup, see
 *                   {@see InlineMarkup}).
 */
enum ExceptionMapping: string
{
    case EXCEPTION = 'EXCEPTION';
    case IO_EXCEPTION = 'IO_EXCEPTION';
    case FILE_NOT_FOUND_EXCEPTION = 'FILE_NOT_FOUND_EXCEPTION';
    case RUNTIME_EXCEPTION = 'RUNTIME_EXCEPTION';
    case ARITHMETIC_EXCEPTION = 'ARITHMETIC_EXCEPTION';
    case ILLEGAL_ARGUMENT_EXCEPTION = 'ILLEGAL_ARGUMENT_EXCEPTION';
    case INDEX_OUT_OF_BOUNDS_EXCEPTION = 'INDEX_OUT_OF_BOUNDS_EXCEPTION';
    case NULL_POINTER_EXCEPTION = 'NULL_POINTER_EXCEPTION';
    case DIVIDE_BY_ZERO_EXCEPTION = 'DIVIDE_BY_ZERO_EXCEPTION';

    // Sourced from Hypertube
    // src/php/hypertube/utils/type/ExceptionType.php::getExceptionCodeByExceptionName.
    // Bare class names only - callers strip namespace prefixes before
    // lookup so '\Foo\IOException' and 'IOException' resolve identically.
    private const array RAW_TO_CATEGORY = [
        'Exception' => 'EXCEPTION',
        'Throwable' => 'EXCEPTION',
        'IOException' => 'IO_EXCEPTION',
        'FileNotFoundException' => 'FILE_NOT_FOUND_EXCEPTION',
        'RuntimeException' => 'RUNTIME_EXCEPTION',
        'ArithmeticError' => 'ARITHMETIC_EXCEPTION',
        'InvalidArgumentException' => 'ILLEGAL_ARGUMENT_EXCEPTION',
        'OutOfBoundsException' => 'INDEX_OUT_OF_BOUNDS_EXCEPTION',
        'OutOfRangeException' => 'INDEX_OUT_OF_BOUNDS_EXCEPTION',
        'TypeError' => 'NULL_POINTER_EXCEPTION',
        'DivisionByZeroError' => 'DIVIDE_BY_ZERO_EXCEPTION',
    ];

    /**
     * Classify a source-language exception name into a canonical
     * category. Returns null when the raw type is not recognized so the
     * caller can leave `COMMENT_THROW.payload[1]` empty and let GCG
     * fall back to the raw spelling in `payload[0]`.
     */
    public static function fromRaw(?string $rawType): ?self
    {
        if ($rawType === null || trim($rawType) === '') {
            return null;
        }
        $bare = ltrim($rawType, '\\');
        $lastSeparator = strrpos($bare, '\\');
        if ($lastSeparator !== false) {
            $bare = substr($bare, $lastSeparator + 1);
        }
        $category = self::RAW_TO_CATEGORY[$bare] ?? null;
        return $category !== null ? self::from($category) : null;
    }

    /**
     * Render this canonical category as the idiomatic PHP class name.
     *
     * Sourced from Hypertube
     * src/php/hypertube/utils/exception/ExceptionThrower.php::throwException
     * switch on ExceptionType. EXCEPTION / IO_EXCEPTION /
     * FILE_NOT_FOUND_EXCEPTION all collapse to RuntimeException because
     * PHP's SPL has no native checked-exception hierarchy on the throw
     * side.
     */
    public function toIdiomatic(): string
    {
        return match ($this) {
            self::EXCEPTION,
            self::IO_EXCEPTION,
            self::FILE_NOT_FOUND_EXCEPTION,
            self::RUNTIME_EXCEPTION => 'RuntimeException',
            self::ARITHMETIC_EXCEPTION => 'ArithmeticError',
            self::ILLEGAL_ARGUMENT_EXCEPTION => 'InvalidArgumentException',
            self::INDEX_OUT_OF_BOUNDS_EXCEPTION => 'OutOfBoundsException',
            self::NULL_POINTER_EXCEPTION => 'TypeError',
            self::DIVIDE_BY_ZERO_EXCEPTION => 'DivisionByZeroError',
        };
    }
}
