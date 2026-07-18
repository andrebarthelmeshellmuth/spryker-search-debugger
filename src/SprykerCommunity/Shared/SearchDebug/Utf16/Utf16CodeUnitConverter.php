<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchDebug\Utf16;

/**
 * Elasticsearch/Lucene token offsets are UTF-16 code units (Java string indexing), NOT Unicode code
 * points — the two diverge as soon as text contains a supplementary-plane character (e.g. an emoji
 * counts as 2 units but 1 code point). Converting to a UTF-16BE representation first (2 bytes per code
 * unit, so unit offsets are exact byte math) makes offset math exact, rather than approximating with the
 * code-point-based `mb_substr()`/`mb_strlen()`.
 *
 * Shared (Client AND Yves layers both need this — see {@see \SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzer},
 * {@see \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighter}, {@see \SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver})
 * so the identical recipe lives in exactly one place instead of three independently-maintained copies.
 */
class Utf16CodeUnitConverter
{
    /**
     * Converts once; a caller that slices the SAME text at several offsets (e.g. one call per matched
     * span) should convert once via this method and reuse the result across every {@see lengthOf()}/
     * {@see slice()} call, rather than paying the UTF-8<->UTF-16 conversion cost again per slice.
     *
     * @param string $text
     *
     * @return string
     */
    public static function toUtf16(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
    }

    /**
     * @param string $textUtf16 Already converted via {@see toUtf16()}.
     *
     * @return int
     */
    public static function lengthOf(string $textUtf16): int
    {
        return (int)(strlen($textUtf16) / 2);
    }

    /**
     * @param string $textUtf16 Already converted via {@see toUtf16()}.
     * @param int $fromCodeUnit
     * @param int $toCodeUnit
     *
     * @return string
     */
    public static function slice(string $textUtf16, int $fromCodeUnit, int $toCodeUnit): string
    {
        $sliceUtf16 = substr($textUtf16, $fromCodeUnit * 2, ($toCodeUnit - $fromCodeUnit) * 2);

        return mb_convert_encoding($sliceUtf16, 'UTF-8', 'UTF-16BE');
    }
}
