<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class AnalysisStageMapper implements AnalysisStageMapperInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface $indexSchemaReader
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface $componentDefinitionFormatter
     */
    public function __construct(
        protected IndexSchemaReaderInterface $indexSchemaReader,
        protected ComponentDefinitionFormatterInterface $componentDefinitionFormatter,
    ) {
    }

    /**
     * Every char filter is a single whole-text transformation (no per-token offsets — char filters run
     * BEFORE tokenization, on the raw character stream), represented here as one pseudo-token spanning
     * the whole filtered text, so the caller can treat every stage — char filters, the tokenizer, and
     * every token filter — uniformly. Confirmed live against this shop's `unit_symbol_normalizer` char
     * filter (`page.json`).
     *
     * A BUILT-IN (non-custom) analyzer reports no tokenizer/tokenfilters breakdown at all — its tokens
     * live directly under `analyzer.tokens` as a single opaque stage, covered by the fallback at the end.
     *
     * Each stage also carries a `definition`: the component's own configuration (e.g.
     * "edge_ngram (min_gram: 2, max_gram: 20)"), looked up by name in the live index schema — `null`
     * when the component is a BUILT-IN Elasticsearch one used as-is (e.g. "lowercase", "standard"),
     * since there is nothing custom to show for those. `componentKind`/`componentName` are the same
     * pair `getComponentConfig()` accepts to re-fetch the FULL, untruncated config server-side — `null`
     * whenever `definition` itself is `null`, since there is then nothing to re-fetch either.
     *
     * @param array<string, mixed> $detail
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}>
     */
    public function mapStages(array $detail): array
    {
        $stages = [];

        foreach ((array)($detail['charfilters'] ?? []) as $charFilter) {
            $filteredText = (string)(($charFilter['filtered_text'] ?? [])[0] ?? '');
            $name = (string)($charFilter['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_CHAR_FILTER,
                'char filter',
                $name,
                [$this->wholeTextAsToken($filteredText)],
            );
        }

        $tokenizerTokens = $this->mapTokens($detail['tokenizer']['tokens'] ?? []);
        if ($tokenizerTokens !== []) {
            $name = (string)($detail['tokenizer']['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_TOKENIZER,
                'tokenizer',
                $name,
                $tokenizerTokens,
            );
        }

        foreach ((array)($detail['tokenfilters'] ?? []) as $tokenFilter) {
            $tokens = $this->mapTokens($tokenFilter['tokens'] ?? []);

            // Deliberately NOT skipped when $tokens is empty (a filter that removed every surviving
            // token — a stop-word list wiping out a whole one-word query is the common case): this stage
            // boundary is exactly what AnalysisTreeBuilder's removed-token detection needs to see.
            // Silently dropping it here would make the PREVIOUS stage look like the pipeline's genuine
            // final output, misreporting a removed token as a real final result — confirmed live: "und"
            // (a German stop word) rendered as if `german_normalization` were its last step, no `∅`
            // marker, because the very next stage (`german_stop_words`, the one that actually removed it)
            // used to be skipped here for having nothing left to show.
            $name = (string)($tokenFilter['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_FILTER,
                'filter',
                $name,
                $tokens,
            );
        }

        if ($stages === [] && isset($detail['analyzer']['tokens'])) {
            $stages[] = [
                'operation' => 'analyzer: ' . ($detail['analyzer']['name'] ?? '?'),
                // A built-in analyzer is used by name only, never customized — nothing to look up.
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'isStem' => false,
                'tokens' => $this->mapTokens($detail['analyzer']['tokens']),
            ];
        }

        return $stages;
    }

    /**
     * @param string $componentKind One of the `IndexSchemaMapper::COMPONENT_KIND_*` constants.
     * @param string $operationLabel
     * @param string $name
     * @param array<array{token: string, startOffset: int, endOffset: int, position: int}> $tokens
     *
     * @return array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}
     */
    protected function buildStage(string $componentKind, string $operationLabel, string $name, array $tokens): array
    {
        $component = $this->indexSchemaReader->findComponent($componentKind, $name);
        $formatted = $this->componentDefinitionFormatter->format($component);

        // Surfaces a stemming filter as "stem: X" instead of the generic "filter: X" every other token
        // filter gets — this was the original trigger for this whole tree feature ("we currently don't
        // display the Stamm"), so it deliberately isn't buried as just another line among many. Heuristic
        // on the component's real ES `type` (its `filter`/`type` setting, e.g. "stemmer", "snowball",
        // "kstem") when the schema resolved one, falling back to the filter's own NAME for a built-in
        // stemmer used without a custom definition (e.g. Elasticsearch's bundled "german_stemmer"). Also
        // exposed as its own `isStem` boolean (not just baked into the `operation` string) so a renderer
        // can style a stem stage distinctly without re-parsing the label text.
        $isStem = $componentKind === IndexSchemaMapper::COMPONENT_KIND_FILTER
            && $this->looksLikeStemmer($component?->getType() ?? $name);

        if ($isStem) {
            $operationLabel = 'stem';
        }

        return [
            'operation' => $operationLabel . ': ' . $name,
            'definition' => $formatted['label'] ?? null,
            'componentKind' => $formatted !== null ? $componentKind : null,
            'componentName' => $formatted !== null ? $name : null,
            'definitionTruncated' => $formatted['truncated'] ?? false,
            'isStem' => $isStem,
            'tokens' => $tokens,
        ];
    }

    /**
     * @param string $typeOrName
     */
    protected function looksLikeStemmer(string $typeOrName): bool
    {
        return preg_match('/stemmer|snowball|kstem/i', $typeOrName) === 1;
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
                // token-stream slot index — see SearchStringAnalyzer::getAnalysisTree()'s docblock); the
                // array index is only ever the fallback for a synthetic char-filter pseudo-token, which
                // carries none.
                'position' => (int)($token['position'] ?? $index),
            ];
        }

        return $result;
    }

    /**
     * Offsets are UTF-16 code units (see `TokenHighlighter`'s own docblock for the same Lucene
     * invariant), via the shared {@see Utf16CodeUnitConverter} — so a char-filter pseudo-token's
     * `endOffset` is directly comparable to a real token's offsets from a later stage.
     *
     * @param string $text
     *
     * @return array{token: string, startOffset: int, endOffset: int, position: int}
     */
    protected function wholeTextAsToken(string $text): array
    {
        return [
            'token' => $text,
            'startOffset' => 0,
            'endOffset' => Utf16CodeUnitConverter::lengthOf(Utf16CodeUnitConverter::toUtf16($text)),
            // No real Lucene position — char filters run before tokenization even exists. See
            // AnalysisTreeBuilder::buildLayers()'s own docblock on why 0 here is only ever a bookkeeping
            // default, never mistaken for a genuine position.
            'position' => 0,
        ];
    }
}
