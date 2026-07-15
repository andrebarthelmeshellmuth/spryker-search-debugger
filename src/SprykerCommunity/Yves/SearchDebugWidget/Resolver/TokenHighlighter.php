<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

class TokenHighlighter implements TokenHighlighterInterface
{
    /**
     * @var string
     */
    protected const HIGHLIGHT_CSS_CLASS = 'token-source-highlight';

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

        usort($matches, fn (array $a, array $b): int => $a['startOffset'] <=> $b['startOffset']);

        $textUtf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        $lengthInCodeUnits = (int)(strlen($textUtf16) / 2);

        $html = '';
        $cursor = 0;

        foreach ($matches as $match) {
            $startOffset = max(0, min($match['startOffset'], $lengthInCodeUnits));
            $endOffset = max($startOffset, min($match['endOffset'], $lengthInCodeUnits));

            if ($startOffset < $cursor) {
                // Overlapping match (shouldn't happen for THIS shop's edge-ngram filter, which always
                // anchors grams at the token's start offset — defensive only, for other analyzer configs
                // this package might run against). Concrete example where it CAN happen: a plain
                // (non-edge) `ngram` filter with min_gram=max_gram=2 on repeated-character text, e.g.
                // "aaaa", yields three "aa" tokens at offsets [0,2), [1,3), [2,4) — pairwise overlapping.
                // Skip it rather than emitting malformed/nested <mark> tags.
                continue;
            }

            $html .= $this->escape($this->sliceCodeUnits($textUtf16, $cursor, $startOffset));
            $html .= sprintf(
                '<mark class="%s">%s</mark>',
                static::HIGHLIGHT_CSS_CLASS,
                $this->escape($this->sliceCodeUnits($textUtf16, $startOffset, $endOffset)),
            );

            $cursor = $endOffset;
        }

        $html .= $this->escape($this->sliceCodeUnits($textUtf16, $cursor, $lengthInCodeUnits));

        return $html;
    }

    /**
     * @param string $textUtf16
     * @param int $fromCodeUnit
     * @param int $toCodeUnit
     *
     * @return string
     */
    protected function sliceCodeUnits(string $textUtf16, int $fromCodeUnit, int $toCodeUnit): string
    {
        $sliceUtf16 = substr($textUtf16, $fromCodeUnit * 2, ($toCodeUnit - $fromCodeUnit) * 2);

        return mb_convert_encoding($sliceUtf16, 'UTF-8', 'UTF-16BE');
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
