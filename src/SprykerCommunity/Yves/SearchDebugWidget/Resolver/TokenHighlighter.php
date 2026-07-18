<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class TokenHighlighter implements TokenHighlighterInterface
{
    /**
     * @var string
     */
    protected const HIGHLIGHT_CSS_CLASS = 'search-debug-highlight';

    /**
     * Builds the highlighted HTML by slicing $text at the given offsets and escaping every slice
     * (matched and unmatched alike) individually, before any `<mark>` markup is added — so the result is
     * safe to render with Twig's `| raw`.
     *
     * Offsets come from Elasticsearch's `_analyze` response. Lucene reports them in UTF-16 code units
     * (Java string indexing), NOT Unicode code points — the two diverge as soon as the text contains a
     * supplementary-plane character (e.g. an emoji counts as 2 units but 1 code point). Slicing is
     * therefore done on a UTF-16BE representation of the text (2 bytes per code unit, so unit offsets
     * are exact byte math), rather than with the code-point-based `mb_substr()`.
     *
     * @param string $text
     * @param array<array{startOffset: int, endOffset: int}> $matches
     *
     * @return string
     */
    public function highlight(string $text, array $matches): string
    {
        if ($matches === []) {
            return $this->escape($text);
        }

        $renderableMatches = $this->filterRenderable($matches);

        $textUtf16 = Utf16CodeUnitConverter::toUtf16($text);
        $lengthInCodeUnits = Utf16CodeUnitConverter::lengthOf($textUtf16);

        $html = '';
        $cursor = 0;

        foreach ($renderableMatches as $match) {
            $startOffset = max(0, min($match['startOffset'], $lengthInCodeUnits));
            $endOffset = max($startOffset, min($match['endOffset'], $lengthInCodeUnits));

            $html .= $this->escape(Utf16CodeUnitConverter::slice($textUtf16, $cursor, $startOffset));
            $html .= sprintf(
                '<mark class="%s">%s</mark>',
                static::HIGHLIGHT_CSS_CLASS,
                $this->escape(Utf16CodeUnitConverter::slice($textUtf16, $startOffset, $endOffset)),
            );

            $cursor = $endOffset;
        }

        $html .= $this->escape(Utf16CodeUnitConverter::slice($textUtf16, $cursor, $lengthInCodeUnits));

        return $html;
    }

    /**
     * {@inheritDoc}
     *
     * Sorted by startOffset, then walked with a cursor: any match whose startOffset falls before the
     * cursor overlaps the previous one and is dropped, exactly as {@see highlight()} itself needs to skip
     * it to avoid emitting malformed/nested `<mark>` tags. Concrete example where overlap CAN happen: a
     * plain (non-edge) `ngram` filter with min_gram=max_gram=2 on repeated-character text, e.g. "aaaa",
     * yields three "aa" tokens at offsets [0,2), [1,3), [2,4) — pairwise overlapping.
     *
     * Exposed as its own method (not just an internal step of {@see highlight()}) so a caller that keeps
     * a SEPARATE list of matches alongside the rendered HTML — e.g. one link built per rendered `<mark>` —
     * can filter through the exact same rule first, keeping both lists in permanent sync instead of
     * `highlight()` silently dropping a match the caller's own list still has an entry for.
     *
     * @template T of array{startOffset: int, endOffset: int}
     *
     * @param array<T> $matches
     *
     * @return array<T>
     */
    public function filterRenderable(array $matches): array
    {
        usort($matches, fn (array $a, array $b): int => $a['startOffset'] <=> $b['startOffset']);

        $renderableMatches = [];
        $cursor = 0;

        foreach ($matches as $match) {
            if ($match['startOffset'] < $cursor) {
                continue;
            }

            $renderableMatches[] = $match;
            $cursor = $match['endOffset'];
        }

        return $renderableMatches;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE);
    }
}
