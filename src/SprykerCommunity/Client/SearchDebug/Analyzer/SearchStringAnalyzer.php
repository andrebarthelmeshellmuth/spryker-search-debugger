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
     * @var \Elastica\Client
     */
    protected Client $elasticaClient;

    /**
     * @var \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface
     */
    protected IndexNameResolverInterface $indexNameResolver;

    /**
     * @var \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface
     */
    protected IndexSchemaReaderInterface $indexSchemaReader;

    /**
     * @var \SprykerCommunity\Client\SearchDebug\SearchDebugConfig
     */
    protected SearchDebugConfig $config;

    /**
     * @var \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface
     */
    protected ComponentDefinitionFormatterInterface $componentDefinitionFormatter;

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface $indexSchemaReader
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface $componentDefinitionFormatter
     */
    public function __construct(
        Client $elasticaClient,
        IndexNameResolverInterface $indexNameResolver,
        IndexSchemaReaderInterface $indexSchemaReader,
        SearchDebugConfig $config,
        ComponentDefinitionFormatterInterface $componentDefinitionFormatter,
    ) {
        $this->elasticaClient = $elasticaClient;
        $this->indexNameResolver = $indexNameResolver;
        $this->indexSchemaReader = $indexSchemaReader;
        $this->config = $config;
        $this->componentDefinitionFormatter = $componentDefinitionFormatter;
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
        } catch (ExceptionInterface $exception) {
            return [];
        }

        return array_column($tokenData, 'token');
    }

    /**
     * Deliberately the INDEX-time analyzer, not the search-time one `getTokens()` above uses: this method
     * answers "does this piece of product text contain token X", and that's determined entirely by
     * what the text was indexed as — the query-time analyzer only ever tokenizes the query string, it
     * never touches document content. This matters whenever an analyzer transforms text asymmetrically
     * between index- and query-time — ngram/edge-ngram filters, decompounding, synonym expansion, and
     * stemming are all common examples — because only the index-time analyzer that actually produced a
     * document's tokens can explain why a query token matched it. E.g., in a basic shop using an
     * edge-ngram index analyzer (search-as-you-type prefix matching), a query token like "öl" can
     * legitimately match a document that only contains "Ölpapier" — the search-time analyzer alone could
     * never explain that match.
     *
     * @param string $text
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTokenOffsets(string $text): array
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
                    'analyzer' => $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface $exception) {
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
        } catch (ExceptionInterface $exception) {
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
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getAnalysisStages(string $text): array
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
                    'analyzer' => $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface $exception) {
            return [];
        }

        return $this->mapAnalysisStages($detail);
    }

    /**
     * Analyzer names are resolved from the LIVE index's schema (see IndexSchemaReader — the cluster's
     * merged mapping is the truth; schema JSON file locations and merge layering are
     * installation-specific), keyed off the `full-text` field. `full-text-boosted` is assumed to share
     * its analyzers — true for this schema, and a simplification the per-element analysis relies on
     * anyway, since document elements are not analyzed per-field.
     *
     * @return string
     */
    protected function resolveSearchAnalyzerName(): string
    {
        return $this->resolveAnalyzerName(
            fn (SearchIndexFieldTransfer $field): ?string => $field->getSearchAnalyzerName(),
        );
    }

    /**
     * @return string
     */
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
     *
     * @return string
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

        return $this->mapTokens($tokens);
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
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    protected function mapAnalysisStages(array $detail): array
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
            if ($tokens === []) {
                continue;
            }

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
                'operation' => 'analyzer: ' . (string)($detail['analyzer']['name'] ?? '?'),
                // A built-in analyzer is used by name only, never customized — nothing to look up.
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => $this->mapTokens($detail['analyzer']['tokens']),
            ];
        }

        return $stages;
    }

    /**
     * @param string $componentKind One of the `IndexSchemaMapper::COMPONENT_KIND_*` constants.
     * @param string $operationLabel
     * @param string $name
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}
     */
    protected function buildStage(string $componentKind, string $operationLabel, string $name, array $tokens): array
    {
        $formatted = $this->componentDefinitionFormatter->format(
            $this->indexSchemaReader->findComponent($componentKind, $name),
        );

        return [
            'operation' => $operationLabel . ': ' . $name,
            'definition' => $formatted['label'] ?? null,
            'componentKind' => $formatted !== null ? $componentKind : null,
            'componentName' => $formatted !== null ? $name : null,
            'definitionTruncated' => $formatted['truncated'] ?? false,
            'tokens' => $tokens,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rawTokens
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    protected function mapTokens(array $rawTokens): array
    {
        $result = [];
        foreach ($rawTokens as $token) {
            if (!isset($token['token'], $token['start_offset'], $token['end_offset'])) {
                continue;
            }

            $result[] = [
                'token' => $token['token'],
                'startOffset' => $token['start_offset'],
                'endOffset' => $token['end_offset'],
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
     * @return array{token: string, startOffset: int, endOffset: int}
     */
    protected function wholeTextAsToken(string $text): array
    {
        return [
            'token' => $text,
            'startOffset' => 0,
            'endOffset' => Utf16CodeUnitConverter::lengthOf(Utf16CodeUnitConverter::toUtf16($text)),
        ];
    }
}
