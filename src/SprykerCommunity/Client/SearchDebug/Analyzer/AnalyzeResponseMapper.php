<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

/**
 * Pure parsing of Elasticsearch's `_analyze` response shape — no HTTP calls, no dependencies beyond the
 * raw response arrays {@see SearchStringAnalyzer} passes in. Split out of that class purely to keep its
 * own responsibility (resolving analyzer names, making the `_analyze` calls, handling their exceptions)
 * separate from this one (turning a raw response into this package's own token/offset shape).
 */
class AnalyzeResponseMapper implements AnalyzeResponseMapperInterface
{
    /**
     * Both the search-time and index-time analyzers are custom (tokenizer + filter chain defined in
     * `page.json`), so Elasticsearch's `explain` response reports `custom_analyzer: true` and nests the
     * tokens under `tokenfilters[]` (one entry per filter, in chain order) rather than under a single
     * `analyzer` key, for either. The LAST filter's tokens are the fully-processed, final tokens —
     * deliberately not hardcoding which index that is (`array_key_last()` instead of e.g. `[0]`), so this
     * keeps working unmodified if a stemming filter is ever appended to either chain: the new filter would
     * simply become the new last entry, its tokens picked up automatically without a code change here.
     * (A BUILT-IN analyzer — e.g. the `standard` fallback — reports no `tokenfilters` at all; its final
     * tokens live under `analyzer.tokens`, covered by the fallback chain below.)
     *
     * @param array<string, mixed> $detail
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function mapTokenDetail(array $detail): array
    {
        $tokenFilters = $detail['tokenfilters'] ?? [];
        $lastFilterKey = array_key_last($tokenFilters);

        $tokens = $lastFilterKey !== null
            ? ($tokenFilters[$lastFilterKey]['tokens'] ?? [])
            : ($detail['analyzer']['tokens'] ?? $detail['tokenizer']['tokens'] ?? []);

        // `position` is bookkeeping mapTokens() adds for AnalysisStageMapper/AnalysisTreeBuilder's own
        // use — never part of this method's public return shape, so it's stripped here rather than
        // leaking into SearchStringAnalyzer::getTokenOffsets()/getTokenOffsetsForTexts().
        return array_map(
            static fn (array $token): array => ['token' => $token['token'], 'startOffset' => $token['startOffset'], 'endOffset' => $token['endOffset']],
            $this->mapTokens($tokens),
        );
    }

    /**
     * @param array<string> $texts
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>|null
     */
    public function rebaseTokensByText(array $texts, array $tokens): ?array
    {
        $boundaries = [];
        $cursor = 0;

        foreach ($texts as $text) {
            $length = Utf16CodeUnitConverter::lengthOf(Utf16CodeUnitConverter::toUtf16($text));
            $boundaries[] = ['text' => $text, 'start' => $cursor, 'end' => $cursor + $length];
            // The single code-unit gap Elasticsearch inserts between consecutive array-`text` values —
            // see this method's caller for the live-confirmed measurement.
            $cursor += $length + 1;
        }

        $tokensByText = array_fill_keys($texts, []);

        foreach ($tokens as $token) {
            $boundary = null;

            foreach ($boundaries as $candidate) {
                if ($token['startOffset'] >= $candidate['start'] && $token['startOffset'] < $candidate['end']) {
                    $boundary = $candidate;

                    break;
                }
            }

            if ($boundary === null) {
                return null;
            }

            $startOffset = $token['startOffset'] - $boundary['start'];
            $endOffset = $token['endOffset'] - $boundary['start'];

            if ($startOffset < 0 || $endOffset > ($boundary['end'] - $boundary['start'])) {
                return null;
            }

            $tokensByText[$boundary['text']][] = [
                'token' => $token['token'],
                'startOffset' => $startOffset,
                'endOffset' => $endOffset,
            ];
        }

        return $tokensByText;
    }

    /**
     * @param array<int, array<string, mixed>> $rawTokens
     *
     * @return array<array{token: string, startOffset: int, endOffset: int, position: int}>
     */
    protected function mapTokens(array $rawTokens): array
    {
        $result = [];
        foreach ($rawTokens as $index => $token) {
            if (!isset($token['token'], $token['start_offset'], $token['end_offset'])) {
                continue;
            }

            $result[] = [
                'token' => $token['token'],
                'startOffset' => $token['start_offset'],
                'endOffset' => $token['end_offset'],
                // Elasticsearch reports `position` on every real tokenizer/filter token (Lucene's own
                // token-stream slot index); the array index is only ever the fallback for a synthetic
                // char-filter pseudo-token, which carries none.
                'position' => (int)($token['position'] ?? $index),
            ];
        }

        return $result;
    }
}
