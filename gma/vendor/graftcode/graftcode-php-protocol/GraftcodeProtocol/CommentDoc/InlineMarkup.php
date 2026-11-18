<?php

declare(strict_types=1);

namespace GraftcodeProtocol\CommentDoc;

/**
 * Neutral in-band inline markup grammar for CommentDoc description fields.
 *
 * See `graftcode_protocol/commentdoc/inline_markup.py` for the canonical
 * specification. Two markers survive the round trip through UGM:
 *
 *   {{ref:Target}}   - type/symbol reference
 *   {{code:expr}}    - inline literal code
 *
 * Every analyzer calls `$format->encode($text)` before writing a description
 * into the UGM payload; every generator calls `$format->decode($text)` before
 * rendering the description back into its target documentation language.
 */
enum InlineMarkup: string
{
    case JAVADOC = 'javadoc';
    case XMLDOC = 'xmldoc';
    case JSDOC = 'jsdoc';
    case PHPDOC = 'phpdoc';
    case RST_GOOGLE = 'rst_google';
    case YARD = 'yard';

    private const string REF_PREFIX = '{{ref:';
    private const string CODE_PREFIX = '{{code:';
    private const string CLOSE = '}}';

    private const string REF_PATTERN = '/\{\{ref:([^}]+)\}\}/';
    private const string CODE_PATTERN = '/\{\{code:([^}]+)\}\}/';

    private const string JAVADOC_LINK = '/\{@link(?:plain)?\s+([^}\s]+)(?:\s+[^}]*)?\}/';
    private const string JAVADOC_CODE = '/\{@code\s+([^}]+)\}/';
    private const string HTML_CODE = '/<code>([^<]+)<\/code>/i';
    private const string HTML_P_OPEN = '/<p\s*\/?>/i';
    private const string HTML_P_CLOSE = '/<\/p\s*>/i';

    private const string XMLDOC_SEE = '/<see(?:also)?\s+cref="([^"]+)"\s*\/>/i';
    private const string XMLDOC_PARAMREF = '/<paramref\s+name="([^"]+)"\s*\/>/i';
    private const string XMLDOC_C = '/<c>([^<]+)<\/c>/i';
    private const string XMLDOC_PARA_OPEN = '/<para\s*\/?>/i';
    private const string XMLDOC_PARA_CLOSE = '/<\/para\s*>/i';
    private const string CREF_PREFIX = '/^[TMFPE]:/';

    private const string JSDOC_LINK = '/\{@link(?:code|plain)?\s+([^}\s|]+)(?:\s*\|[^}]*)?\}/';
    private const string BACKTICK = '/`([^`\n]+)`/';
    private const string DOUBLE_BACKTICK = '/``([^`\n]+)``/';
    private const string PHPDOC_LINK = '/\{@(?:link|see)\s+([^}\s]+)(?:\s+[^}]*)?\}/';
    private const string RST_ROLE = '/:(?:py:)?(?:class|func|meth|attr|mod|obj|exc|data):`([^`]+)`/';
    private const string YARD_LINK = '/\{([A-Z][A-Za-z0-9_]*(?:[#.][A-Za-z0-9_]+)?)(?:\s+[^}]+)?\}/';

    /**
     * Encode a source-language doc-comment description into the neutral
     * UGM markup, replacing format-specific references and inline code
     * with `{{ref:...}}` / `{{code:...}}` markers.
     */
    public function encode(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return match ($this) {
            self::JAVADOC => self::encodeJavadocImpl($text),
            self::XMLDOC => self::encodeXmldocImpl($text),
            self::JSDOC => self::encodeJsdocImpl($text),
            self::PHPDOC => self::encodePhpdocImpl($text),
            self::RST_GOOGLE => self::encodeRstGoogleImpl($text),
            self::YARD => self::encodeYardImpl($text),
        };
    }

    /**
     * Decode neutral UGM markup into this format's idiomatic doc-comment
     * representation.
     */
    public function decode(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return match ($this) {
            self::JAVADOC => self::decodeToJavadocImpl($text),
            self::XMLDOC => self::decodeToXmldocImpl($text),
            self::JSDOC => self::decodeToJsdocImpl($text),
            self::PHPDOC => self::decodeToPhpdocImpl($text),
            self::RST_GOOGLE => self::decodeToRstGoogleImpl($text),
            self::YARD => self::decodeToYardImpl($text),
        };
    }

    private static function ref(string $target): string
    {
        return self::REF_PREFIX . trim($target) . self::CLOSE;
    }

    private static function code(string $expr): string
    {
        return self::CODE_PREFIX . trim($expr) . self::CLOSE;
    }

    private static function stripCref(string $cref): string
    {
        $trimmed = preg_replace(self::CREF_PREFIX, '', $cref) ?? $cref;
        if (str_contains($trimmed, '.') && !str_contains($trimmed, '(')) {
            $dot = strrpos($trimmed, '.');
            return substr($trimmed, $dot + 1);
        }
        return $trimmed;
    }

    private static function replaceCallback(string $pattern, string $input, callable $callback): string
    {
        $result = preg_replace_callback($pattern, $callback, $input);
        return $result ?? $input;
    }

    private static function encodeJavadocImpl(string $text): string
    {
        $out = self::replaceCallback(self::JAVADOC_LINK, $text, fn ($m) => self::ref($m[1]));
        $out = self::replaceCallback(self::JAVADOC_CODE, $out, fn ($m) => self::code($m[1]));
        $out = self::replaceCallback(self::HTML_CODE, $out, fn ($m) => self::code($m[1]));
        $out = preg_replace(self::HTML_P_OPEN, "\n\n", $out) ?? $out;
        $out = preg_replace(self::HTML_P_CLOSE, '', $out) ?? $out;
        return $out;
    }

    private static function encodeXmldocImpl(string $text): string
    {
        $out = self::replaceCallback(self::XMLDOC_SEE, $text, fn ($m) => self::ref(self::stripCref($m[1])));
        $out = self::replaceCallback(self::XMLDOC_PARAMREF, $out, fn ($m) => self::code($m[1]));
        $out = self::replaceCallback(self::XMLDOC_C, $out, fn ($m) => self::code($m[1]));
        $out = preg_replace(self::XMLDOC_PARA_OPEN, "\n\n", $out) ?? $out;
        $out = preg_replace(self::XMLDOC_PARA_CLOSE, '', $out) ?? $out;
        return $out;
    }

    private static function encodeJsdocImpl(string $text): string
    {
        $out = self::replaceCallback(self::JSDOC_LINK, $text, fn ($m) => self::ref($m[1]));
        $out = self::replaceCallback(self::BACKTICK, $out, fn ($m) => self::code($m[1]));
        return $out;
    }

    private static function encodePhpdocImpl(string $text): string
    {
        $out = self::replaceCallback(self::PHPDOC_LINK, $text, fn ($m) => self::ref($m[1]));
        $out = self::replaceCallback(self::BACKTICK, $out, fn ($m) => self::code($m[1]));
        return $out;
    }

    private static function encodeRstGoogleImpl(string $text): string
    {
        $out = self::replaceCallback(self::RST_ROLE, $text, fn ($m) => self::ref($m[1]));
        $out = self::replaceCallback(self::DOUBLE_BACKTICK, $out, fn ($m) => self::code($m[1]));
        return $out;
    }

    private static function encodeYardImpl(string $text): string
    {
        $out = self::replaceCallback(self::YARD_LINK, $text, fn ($m) => self::ref($m[1]));
        $out = self::replaceCallback(self::BACKTICK, $out, fn ($m) => self::code($m[1]));
        return $out;
    }

    private static function decodeToJavadocImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, fn ($m) => '{@link ' . trim($m[1]) . '}');
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '{@code ' . trim($m[1]) . '}');
        return $out;
    }

    private static function decodeToXmldocImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, fn ($m) => '<see cref="' . trim($m[1]) . '"/>');
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '<c>' . trim($m[1]) . '</c>');
        return $out;
    }

    private static function decodeToJsdocImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, fn ($m) => '{@link ' . trim($m[1]) . '}');
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '`' . trim($m[1]) . '`');
        return $out;
    }

    private static function decodeToPhpdocImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, fn ($m) => '{@link ' . trim($m[1]) . '}');
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '`' . trim($m[1]) . '`');
        return $out;
    }

    private static function rstRoleFor(string $target): string
    {
        $first = $target !== '' ? $target[0] : '';
        $firstUpper = ctype_upper($first);
        if (str_contains($target, '#') || (str_contains($target, '.') && $firstUpper)) {
            return 'meth';
        }
        if ($firstUpper) {
            return 'class';
        }
        return 'func';
    }

    private static function decodeToRstGoogleImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, function ($m) {
            $target = trim($m[1]);
            $role = self::rstRoleFor($target);
            return ':' . $role . ':`' . str_replace('#', '.', $target) . '`';
        });
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '``' . trim($m[1]) . '``');
        return $out;
    }

    private static function decodeToYardImpl(string $text): string
    {
        $out = self::replaceCallback(self::REF_PATTERN, $text, fn ($m) => '{' . trim($m[1]) . '}');
        $out = self::replaceCallback(self::CODE_PATTERN, $out, fn ($m) => '`' . trim($m[1]) . '`');
        return $out;
    }
}
