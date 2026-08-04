<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Schema;

use Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;
use Generated\Shared\Transfer\SearchIndexSchemaTransfer;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;

class IndexSchemaReader implements IndexSchemaReaderInterface
{
    /**
     * @var string
     */
    protected const SETTINGS_KEY_ANALYSIS = 'analysis';

    /**
     * Memoized per index name for the lifetime of the PHP process. Static deliberately: consumers are
     * constructed freshly per client call (Spryker factory convention), so an instance cache would never
     * hit — while the schema is stable within a request, and one page load can trigger many analyze
     * calls (same pattern as the vendor `IndexNameResolver`'s static store cache). Failed reads are NOT
     * cached, so a transient Elasticsearch hiccup doesn't pin an empty schema for the whole process.
     *
     * @var array<string, \Generated\Shared\Transfer\SearchIndexSchemaTransfer>
     */
    protected static array $searchIndexSchemaTransferCache = [];

    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapperInterface $indexSchemaMapper
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     */
    public function __construct(
        protected Client $elasticaClient,
        protected IndexNameResolverInterface $indexNameResolver,
        protected IndexSchemaMapperInterface $indexSchemaMapper,
        protected SearchDebugConfig $config,
    ) {
    }

    public function getPageIndexSchema(): SearchIndexSchemaTransfer
    {
        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        if (isset(static::$searchIndexSchemaTransferCache[$indexName])) {
            return static::$searchIndexSchemaTransferCache[$indexName];
        }

        try {
            $index = $this->elasticaClient->getIndex($indexName);
            $mapping = $index->getMapping();
            $analysisSettings = (array)($index->getSettings()->get(static::SETTINGS_KEY_ANALYSIS) ?: []);
        } catch (ExceptionInterface) {
            // Fail-soft, uncached: an empty schema makes consumers fall back to Elasticsearch's
            // "standard" analyzer — the same posture as the analyze calls themselves, which return
            // empty results when the cluster is unreachable.
            return (new SearchIndexSchemaTransfer())->setIndexName($indexName);
        }

        $searchIndexSchemaTransfer = $this->indexSchemaMapper
            ->mapToSearchIndexSchemaTransfer($indexName, $mapping, $analysisSettings);

        static::$searchIndexSchemaTransferCache[$indexName] = $searchIndexSchemaTransfer;

        return $searchIndexSchemaTransfer;
    }

    /**
     * @param string $componentKind One of the `IndexSchemaMapper::COMPONENT_KIND_*` constants.
     * @param string $componentName
     */
    public function findComponent(string $componentKind, string $componentName): ?SearchAnalysisComponentTransfer
    {
        $indexSchema = $this->getPageIndexSchema();

        $components = match ($componentKind) {
            IndexSchemaMapper::COMPONENT_KIND_TOKENIZER => $indexSchema->getTokenizers(),
            IndexSchemaMapper::COMPONENT_KIND_FILTER => $indexSchema->getFilters(),
            IndexSchemaMapper::COMPONENT_KIND_CHAR_FILTER => $indexSchema->getCharFilters(),
            default => [],
        };

        foreach ($components as $component) {
            if ($component->getName() === $componentName) {
                return $component;
            }
        }

        return null;
    }
}
