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
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;

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
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface $indexSchemaReader
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     */
    public function __construct(
        Client $elasticaClient,
        IndexNameResolverInterface $indexNameResolver,
        IndexSchemaReaderInterface $indexSchemaReader,
        SearchDebugConfig $config,
    ) {
        $this->elasticaClient = $elasticaClient;
        $this->indexNameResolver = $indexNameResolver;
        $this->indexSchemaReader = $indexSchemaReader;
        $this->config = $config;
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
     * document's tokens can explain why a query token matched it. E.g., in this reference shop's config,
     * the index analyzer edge-ngrams every word (search-as-you-type prefix matching), so a query token
     * like "öl" can legitimately match a document that only contains "Ölpapier" — the search-time
     * analyzer alone could never explain that match.
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
        foreach ($this->indexSchemaReader->getPageIndexSchema()->getFields() as $searchIndexFieldTransfer) {
            if ($searchIndexFieldTransfer->getName() === PageIndexMap::FULL_TEXT) {
                return $searchIndexFieldTransfer->getSearchAnalyzerName() ?? IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
            }
        }

        return IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
    }

    /**
     * @return string
     */
    protected function resolveIndexAnalyzerName(): string
    {
        foreach ($this->indexSchemaReader->getPageIndexSchema()->getFields() as $searchIndexFieldTransfer) {
            if ($searchIndexFieldTransfer->getName() === PageIndexMap::FULL_TEXT) {
                return $searchIndexFieldTransfer->getAnalyzerName() ?? IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
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

        $result = [];
        foreach ($tokens as $token) {
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
}
