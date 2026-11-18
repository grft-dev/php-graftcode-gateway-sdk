<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

/**
 * Runtime configuration for the Module Analyzer (PHP).
 *
 * Exposes a small set of booleans the analyzer reads at construction
 * time to decide what to emit in the UGM. Today the only flag is
 * {@see shouldEmitCodeExamples} which gates the
 * `COMMENT_EXAMPLES_ARRAY` node and the `raw` payload entry of
 * `COMMENT_SOURCE` -- both of which can carry verbatim source-code
 * fragments.
 *
 * The default is `false` (no code emitted) because we do not want
 * source-code fragments to leak through UGM by default. The flag can
 * be enabled per-run via the `GRAFTCODE_GMA_EMIT_CODE_EXAMPLES`
 * environment variable, set to one of `1` / `true` / `yes`
 * (case-insensitive).
 *
 * The flag is read from the environment on every call rather than
 * cached, so tests can flip it with `putenv()` without juggling
 * autoloader order.
 */
final class AnalyzerConfig
{
    public const string ENV_EMIT_CODE_EXAMPLES = 'GRAFTCODE_GMA_EMIT_CODE_EXAMPLES';

    private const array TRUTHY = ['1', 'true', 'yes', 'on'];

    /**
     * Returns `true` iff the analyzer is allowed to embed source code
     * in UGM. Defaults to `false` (source-code suppression).
     */
    public static function shouldEmitCodeExamples(): bool
    {
        $raw = getenv(self::ENV_EMIT_CODE_EXAMPLES);
        if (false === $raw) {
            return false;
        }

        return in_array(strtolower(trim($raw)), self::TRUTHY, true);
    }
}
