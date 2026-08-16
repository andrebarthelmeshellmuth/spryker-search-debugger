<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\SearchIndexFieldTransfer;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class SearchStringAnalyzer implements SearchStringAnalyzerInterface
{
    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface $indexSchemaReader
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface $componentDefinitionFormatter
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisTreeBuilderInterface $analysisTreeBuilder
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisStageMapperInterface $analysisStageMapper
     */
    public function __construct(
        protected Client $elasticaClient,
        protected IndexNameResolverInterface $indexNameResolver,
        protected IndexSchemaReaderInterface $indexSchemaReader,
        protected SearchDebugConfig $config,
        protected ComponentDefinitionFormatterInterface $componentDefinitionFormatter,
        protected AnalysisTreeBuilderInterface $analysisTreeBuilder,
        protected AnalysisStageMapperInterface $analysisStageMapper,
    ) {
    }

    /**
     * @param string $searchString
     *
     * @return array<string>
     */
    public function getTokens(string $searchString): array
    {
        if ($searchString === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $tokenData = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $searchString,
                    'analyzer' => $this->resolveSearchAnalyzerName(),
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return array_column($tokenData, 'token');
    }

    /**
     * Deliberately the INDEX-time analyzer BY DEFAULT, not the search-time one `getTokens()` above uses:
     * this method answers "does this piece of product text contain token X", and that's determined
     * entirely by what the text was indexed as — the query-time analyzer only ever tokenizes the query
     * string, it never touches document content. This matters whenever an analyzer transforms text
     * asymmetrically between index- and query-time — ngram/edge-ngram filters, decompounding, synonym
     * expansion, and stemming are all common examples — because only the index-time analyzer that
     * actually produced a document's tokens can explain why a query token matched it. E.g., in a basic
     * shop using an edge-ngram index analyzer (search-as-you-type prefix matching), a query token like
     * "öl" can legitimately match a document that only contains "Ölpapier" — the search-time analyzer
     * alone could never explain that match.
     *
     * `$useSearchAnalyzer` exists for the opposite question: tracing how a QUERY string itself (not
     * product content) was tokenized — the text in that case was never indexed, only searched, so the
     * search-time analyzer is the only one that actually processed it.
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTokenOffsets(string $text, bool $useSearchAnalyzer = false): array
    {
        if ($text === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $text,
                    'analyzer' => $useSearchAnalyzer ? $this->resolveSearchAnalyzerName() : $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return $this->mapTokenDetail($detail);
    }

    /**
     * Same as {@see getTokenOffsets()}, batched into ONE `_analyze` call for several distinct texts
     * instead of one call per text — e.g. every element of a product's document (title, every concrete
     * name/SKU, descriptions, category names, merchant name) can be analyzed in a single round trip
     * instead of one blocking HTTP call per element.
     *
     * Elasticsearch's `_analyze` API accepts an array for `text`, treating it like a multi-valued field:
     * offsets continue cumulatively across the whole array rather than resetting to 0 per text, with
     * exactly ONE code-unit gap inserted between consecutive texts (confirmed live against this package's
     * real index: two 2-character texts analyzed together report offsets [0,2) and [3,5), never
     * [0,2)/[2,4)) — {@see rebaseTokensByText()} uses that gap to split the flat token list back into
     * one list per original text, with offsets rebased to be relative to THAT text again, exactly as if
     * it had been analyzed alone. If any resulting offset ever falls outside its own text's bounds (the
     * 1-unit-gap assumption not holding against some other Elasticsearch version/configuration), this
     * degrades to one {@see getTokenOffsets()} call per text instead of trusting a possibly-misaligned
     * batched result — the batching is a pure optimization, never a correctness trade-off.
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getTokenOffsetsForTexts(array $texts): array
    {
        $texts = array_values(array_unique(array_filter($texts, fn (string $text): bool => $text !== '')));

        if ($texts === []) {
            return [];
        }

        if (count($texts) === 1) {
            return [$texts[0] => $this->getTokenOffsets($texts[0])];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $texts,
                    'analyzer' => $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return array_fill_keys($texts, []);
        }

        $tokensByText = $this->rebaseTokensByText($texts, $this->mapTokenDetail($detail));

        if ($tokensByText === null) {
            $tokensByText = [];
            foreach ($texts as $text) {
                $tokensByText[$text] = $this->getTokenOffsets($text);
            }
        }

        return $tokensByText;
    }

    /**
     * @param array<string> $texts
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>|null Null when
     *   a rebased offset falls outside its own text's bounds — the gap assumption {@see getTokenOffsetsForTexts()}
     *   documents didn't hold for this response, and the caller should fall back to one call per text.
     */
    protected function rebaseTokensByText(array $texts, array $tokens): ?array
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
     * Full per-stage breakdown of the index-time analyzer's pipeline — every char filter (whole-text
     * transformations, before tokenization), the tokenizer, and every token filter, in chain order.
     * `getTokenOffsets()` above only needs the FINAL stage's tokens; this keeps every intermediate
     * stage instead of collapsing to the last one, so a caller can reconstruct the transformation PATH
     * from a document's raw field text to one specific matched token (walking stage by stage and
     * matching offsets — see `SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver`).
     *
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}>
     */
    public function getAnalysisStages(string $text, bool $useSearchAnalyzer = false): array
    {
        $stages = $this->fetchAnalysisStages($text, $useSearchAnalyzer);

        // `position` is bookkeeping getAnalysisTree()'s own pipeline needs (see fetchAnalysisStages()'s
        // docblock) — never part of THIS method's public return shape, so it's stripped here.
        foreach ($stages as &$stage) {
            $stage['tokens'] = array_map(
                static fn (array $token): array => ['token' => $token['token'], 'startOffset' => $token['startOffset'], 'endOffset' => $token['endOffset']],
                $stage['tokens'],
            );
        }
        unset($stage);

        return $stages;
    }

    /**
     * Same underlying stage breakdown {@see getAnalysisStages()} returns, kept for internal use by
     * {@see getAnalysisTree()}'s own pipeline — the ONLY difference is that each token here still
     * carries its `position` (AnalysisTreeBuilder needs it to link tokens across stages; see that
     * class's own docblock), which {@see getAnalysisStages()} strips before returning to its own
     * callers since it was never part of that method's documented public shape.
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}>
     */
    protected function fetchAnalysisStages(string $text, bool $useSearchAnalyzer): array
    {
        if ($text === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $text,
                    'analyzer' => $useSearchAnalyzer ? $this->resolveSearchAnalyzerName() : $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return $this->analysisStageMapper->mapStages($detail);
    }

    /**
     * A branching pipeline diagram for a piece of text, laid out by STAGE rather than by lineage: one
     * "row" per analyzer stage (char filter, tokenizer, or token filter, in chain order — exactly
     * {@see getAnalysisStages()}'s own stage list), each carrying every token alive at that point, plus a
     * flat list of parent→child EDGES connecting a row's tokens to the specific tokens they produced in
     * the NEXT row. This is the same tree {@see getAnalysisStages()}'s data always implied — just
     * rank-layered (grouped by depth-from-origin) instead of nested by indentation, because depth here IS
     * "which stage produced this token": a stage's own output always re-emits even an UNCHANGED token
     * (Elasticsearch's `_analyze` response includes every token at every stage, touched or not), so no
     * edge ever needs to skip a row.
     *
     * Built entirely from the SAME `_analyze?explain=true` stages {@see getAnalysisStages()} already
     * returns — no new Elasticsearch call. Elasticsearch's own `position` field (present on every
     * tokenizer/filter token, Lucene's encoding of "which slot in the token stream this occupies") is the
     * link between consecutive stages: two tokens in neighboring stages that share the same `position`
     * are treated as parent/child. This is a correlation, not a real parent pointer (the `_analyze` API
     * exposes none) — confirmed sufficient in practice against this project's own `search_debug_synonyms`
     * (1 token in, 4 out, at two positions) and `search_debug_decompound` (1 token in, 2 out, same
     * position) filters. It can misattribute lineage only when a single stage emits more than one token
     * at the SAME position with DIFFERENT text (unresolvable from `position` alone) — accepted as a known
     * limitation rather than solved with per-filter-type special-casing. A token whose position has no
     * match in the previous row simply has no incoming edge, rather than being silently dropped.
     *
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function getAnalysisTree(string $text, bool $useSearchAnalyzer = false): array
    {
        return $this->analysisTreeBuilder->build($this->fetchAnalysisStages($text, $useSearchAnalyzer));
    }

    /**
     * Analyzer names are resolved from the LIVE index's schema (see IndexSchemaReader — the cluster's
     * merged mapping is the truth; schema JSON file locations and merge layering are
     * installation-specific), keyed off the `full-text` field. `full-text-boosted` is assumed to share
     * its analyzers — true for this schema, and a simplification the per-element analysis relies on
     * anyway, since document elements are not analyzed per-field.
     */
    protected function resolveSearchAnalyzerName(): string
    {
        return $this->resolveAnalyzerName(
            fn (SearchIndexFieldTransfer $field): ?string => $field->getSearchAnalyzerName(),
        );
    }

    protected function resolveIndexAnalyzerName(): string
    {
        return $this->resolveAnalyzerName(
            fn (SearchIndexFieldTransfer $field): ?string => $field->getAnalyzerName(),
        );
    }

    /**
     * Shared lookup behind {@see resolveSearchAnalyzerName()} and {@see resolveIndexAnalyzerName()} — the
     * two differ only in which analyzer-name getter they read off the matched `full-text` field.
     *
     * @param callable(\Generated\Shared\Transfer\SearchIndexFieldTransfer): (string|null) $getAnalyzerName
     */
    protected function resolveAnalyzerName(callable $getAnalyzerName): string
    {
        foreach ($this->indexSchemaReader->getPageIndexSchema()->getFields() as $searchIndexFieldTransfer) {
            if ($searchIndexFieldTransfer->getName() === PageIndexMap::FULL_TEXT) {
                return $getAnalyzerName($searchIndexFieldTransfer) ?? IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
            }
        }

        return IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
    }

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
    protected function mapTokenDetail(array $detail): array
    {
        $tokenFilters = $detail['tokenfilters'] ?? [];
        $lastFilterKey = array_key_last($tokenFilters);

        $tokens = $lastFilterKey !== null
            ? ($tokenFilters[$lastFilterKey]['tokens'] ?? [])
            : ($detail['analyzer']['tokens'] ?? $detail['tokenizer']['tokens'] ?? []);

        // `position` is bookkeeping mapTokens() adds for AnalysisStageMapper/AnalysisTreeBuilder's own
        // use (see mapTokens()'s own docblock) — never part of this method's public return shape, so it's
        // stripped here rather than leaking into getTokenOffsets()/getTokenOffsetsForTexts().
        return array_map(
            static fn (array $token): array => ['token' => $token['token'], 'startOffset' => $token['startOffset'], 'endOffset' => $token['endOffset']],
            $this->mapTokens($tokens),
        );
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
                // token-stream slot index — see getAnalysisTree()'s docblock); the array index is only
                // ever the fallback for a synthetic char-filter pseudo-token, which carries none.
                'position' => (int)($token['position'] ?? $index),
            ];
        }

        return $result;
    }
}
