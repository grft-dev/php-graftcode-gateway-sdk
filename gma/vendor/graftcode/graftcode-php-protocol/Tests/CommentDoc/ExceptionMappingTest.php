<?php

declare(strict_types=1);

namespace Tests\Utils;

use GraftcodeProtocol\CommentDoc\ExceptionMapping;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};

/**
 * Tests for `GraftcodeProtocol\CommentDoc\ExceptionMapping`.
 *
 * The module mirrors Hypertube SDK's `ExceptionType` enum so the test
 * suite pins both directions of the mapping:
 *
 *   raw class name  -->  canonical category  -->  idiomatic class name
 *
 * A "round-trip" via the neutral category is the explicit goal: every
 * known source raw type, once classified, must yield a non-empty
 * idiomatic target. Categories absent from one of the tables are dead
 * code -- we trip the test instead of letting them silently produce ''.
 */
final class ExceptionMappingTest extends TestCase
{
    #[Group('unit')]
    public function testEveryCategoryHasIdiomaticRendering(): void
    {
        // Each of the 9 canonical categories must render to a non-empty PHP
        // class name; otherwise GCG would emit a blank `@throws` payload
        // at runtime.
        foreach (ExceptionMapping::cases() as $category) {
            $this->assertNotEmpty(
                $category->toIdiomatic(),
                "category '{$category->value}' has no idiomatic rendering in PHP."
            );
        }
    }

    #[Group('unit')]
    public function testPhpRawTypesClassifyToKnownCategory(): void
    {
        $expectations = [
            'Exception' => ExceptionMapping::EXCEPTION,
            'Throwable' => ExceptionMapping::EXCEPTION,
            'IOException' => ExceptionMapping::IO_EXCEPTION,
            'FileNotFoundException' => ExceptionMapping::FILE_NOT_FOUND_EXCEPTION,
            'RuntimeException' => ExceptionMapping::RUNTIME_EXCEPTION,
            'ArithmeticError' => ExceptionMapping::ARITHMETIC_EXCEPTION,
            'InvalidArgumentException' => ExceptionMapping::ILLEGAL_ARGUMENT_EXCEPTION,
            'OutOfBoundsException' => ExceptionMapping::INDEX_OUT_OF_BOUNDS_EXCEPTION,
            'OutOfRangeException' => ExceptionMapping::INDEX_OUT_OF_BOUNDS_EXCEPTION,
            'TypeError' => ExceptionMapping::NULL_POINTER_EXCEPTION,
            'DivisionByZeroError' => ExceptionMapping::DIVIDE_BY_ZERO_EXCEPTION,
        ];

        foreach ($expectations as $raw => $expectedCategory) {
            $this->assertSame(
                $expectedCategory,
                ExceptionMapping::fromRaw($raw),
                "raw '{$raw}' should classify to {$expectedCategory->value}"
            );
        }
    }

    /**
     * Unknown raw types must NOT collapse to EXCEPTION silently; that
     * would force every undocumented throw into the most generic bucket.
     * We return null so the GCG falls back to the raw spelling in
     * payload[0] (preserving source-language fidelity).
     */
    #[Group('unit')]
    public function testUnknownRawTypeReturnsNull(): void
    {
        $this->assertNull(ExceptionMapping::fromRaw('SomeCustomException'));
        $this->assertNull(ExceptionMapping::fromRaw(''));
        $this->assertNull(ExceptionMapping::fromRaw(null));
        $this->assertNull(ExceptionMapping::fromRaw('   '));
    }

    /**
     * `\Foo\IOException` and `IOException` resolve identically; we only
     * key on the bare class name (analyzer never strips them before
     * lookup). PHP uses backslash as the namespace separator.
     */
    #[Group('unit')]
    public function testNamespaceQualifiedNamesStripToBareClass(): void
    {
        $this->assertSame(
            ExceptionMapping::ILLEGAL_ARGUMENT_EXCEPTION,
            ExceptionMapping::fromRaw('\Foo\InvalidArgumentException')
        );
        $this->assertSame(
            ExceptionMapping::ILLEGAL_ARGUMENT_EXCEPTION,
            ExceptionMapping::fromRaw('MyCompany\MyLib\InvalidArgumentException')
        );
        $this->assertSame(
            ExceptionMapping::IO_EXCEPTION,
            ExceptionMapping::fromRaw('\IOException')
        );
    }

    /**
     * For PHP -> PHP the round trip must converge for canonical inputs:
     * raw -> cat -> idiomatic -> cat'. cat' should equal cat for raw
     * types that match their idiomatic target.
     *
     * Note: EXCEPTION / IO_EXCEPTION / FILE_NOT_FOUND_EXCEPTION all
     * collapse to "RuntimeException" because PHP's SPL has no native
     * checked-exception hierarchy on the throw side (matches Hypertube's
     * own table). Those categories are intentionally excluded from the
     * round-trip sample; the documented one-way collapse is asserted in
     * a dedicated test below.
     */
    #[Group('unit')]
    public function testRoundTripPhpNativeExceptionsPreservesCategory(): void
    {
        $samples = [
            'RuntimeException',
            'ArithmeticError',
            'InvalidArgumentException',
            'OutOfBoundsException',
            'TypeError',
            'DivisionByZeroError',
        ];
        foreach ($samples as $raw) {
            $category = ExceptionMapping::fromRaw($raw);
            $this->assertNotNull($category, "raw '{$raw}' should classify");
            $idiomatic = $category->toIdiomatic();
            $categoryAgain = ExceptionMapping::fromRaw($idiomatic);
            $this->assertSame(
                $category,
                $categoryAgain,
                "round-trip diverges for '{$raw}': " .
                "{$category->value} -> '{$idiomatic}' -> " .
                ($categoryAgain?->value ?? 'null')
            );
        }
    }

    /**
     * Documents the intentional sub-hierarchy collapse: EXCEPTION,
     * IO_EXCEPTION, FILE_NOT_FOUND_EXCEPTION and RUNTIME_EXCEPTION all
     * render as `RuntimeException` because PHP's SPL conflates them on
     * the throw side. The category is preserved on the wire; only the
     * idiomatic rendering converges.
     */
    #[Group('unit')]
    public function testRuntimeExceptionAbsorbsRuntimeFamily(): void
    {
        foreach ([
            ExceptionMapping::EXCEPTION,
            ExceptionMapping::IO_EXCEPTION,
            ExceptionMapping::FILE_NOT_FOUND_EXCEPTION,
            ExceptionMapping::RUNTIME_EXCEPTION,
        ] as $category) {
            $this->assertSame('RuntimeException', $category->toIdiomatic());
        }
    }

    /**
     * Lock the canonical category names so downstream consumers (other
     * runtimes, generated code) never see surprise renames.
     */
    #[Group('unit')]
    public function testCategoryNamesAreStableCanonicalStrings(): void
    {
        $expected = [
            'ARITHMETIC_EXCEPTION',
            'DIVIDE_BY_ZERO_EXCEPTION',
            'EXCEPTION',
            'FILE_NOT_FOUND_EXCEPTION',
            'ILLEGAL_ARGUMENT_EXCEPTION',
            'INDEX_OUT_OF_BOUNDS_EXCEPTION',
            'IO_EXCEPTION',
            'NULL_POINTER_EXCEPTION',
            'RUNTIME_EXCEPTION',
        ];
        $actual = array_map(fn (ExceptionMapping $c) => $c->value, ExceptionMapping::cases());
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
