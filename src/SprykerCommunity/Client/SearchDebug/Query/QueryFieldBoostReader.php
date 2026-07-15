<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Query;

use Elastica\Exception\ExceptionInterface;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;

class QueryFieldBoostReader implements QueryFieldBoostReaderInterface
{
    /**
     * @var string
     */
    protected const PARAM_QUERY = 'query';

    /**
     * @var string
     */
    protected const PARAM_MUST = 'must';

    /**
     * @var string
     */
    protected const PARAM_FIELDS = 'fields';

    /**
     * Request-scoped, not per-instance: `SearchDebugQueryExpanderPlugin` (which has the live Query object)
     * and `SearchDebugResultFormatterPlugin` (which needs the captured values later, when building its
     * output) are separate plugin instances with no shared constructor-injected state — Spryker plugin
     * stacks are `new Plugin()` literals registered in a DependencyProvider, not container-wired. A static
     * property, implicitly scoped to one request because the query-build -> execute -> format-results
     * pipeline always runs sequentially within a single PHP process, is the same pattern
     * `Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory` already uses for
     * connection caching (see `SearchDebugFactory::getElasticaClient()`).
     *
     * @var array<string, int>
     */
    protected static array $fieldBoosts = [];

    /**
     * {@inheritDoc}
     *
     * Reads the real `multi_match` clause off the query that is ABOUT TO RUN, via the same accessor path
     * `Spryker\Client\SearchElasticsearch\Plugin\QueryExpander\FuzzyQueryExpanderPlugin` uses to reach the
     * identical clause (`$query->getQuery()` -> bool "must"[0] -> the `MultiMatch`) — so this makes no
     * assumption about query shape beyond what that core plugin already assumes for the same purpose.
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     *
     * @return array<string, int>
     */
    public function captureFromQuery(QueryInterface $searchQuery): array
    {
        try {
            static::$fieldBoosts = $this->extractFieldBoosts($searchQuery);
        } catch (ExceptionInterface $exception) {
            static::$fieldBoosts = [];
        }

        return static::$fieldBoosts;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
     */
    public function getFieldBoosts(): array
    {
        return static::$fieldBoosts;
    }

    /**
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     *
     * @return array<string, int>
     */
    protected function extractFieldBoosts(QueryInterface $searchQuery): array
    {
        $query = $searchQuery->getSearchQuery();

        if (!$query instanceof Query || !$query->hasParam(static::PARAM_QUERY)) {
            return [];
        }

        $boolQuery = $query->getQuery();

        if (!$boolQuery instanceof BoolQuery || !$boolQuery->hasParam(static::PARAM_MUST)) {
            return [];
        }

        $multiMatch = $boolQuery->getParam(static::PARAM_MUST)[0] ?? null;

        if (!$multiMatch instanceof MultiMatch || !$multiMatch->hasParam(static::PARAM_FIELDS)) {
            return [];
        }

        $fieldBoosts = [];
        foreach ((array)$multiMatch->getParam(static::PARAM_FIELDS) as $field) {
            [$fieldName, $boost] = $this->splitFieldBoost((string)$field);
            $fieldBoosts[$fieldName] = $boost;
        }

        return $fieldBoosts;
    }

    /**
     * @param string $field
     *
     * @return array{0: string, 1: int}
     */
    protected function splitFieldBoost(string $field): array
    {
        if (!str_contains($field, '^')) {
            return [$field, 1];
        }

        [$fieldName, $boost] = explode('^', $field, 2);

        return [$fieldName, (int)$boost];
    }
}
