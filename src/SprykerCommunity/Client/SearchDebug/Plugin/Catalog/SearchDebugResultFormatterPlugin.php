<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Plugin\Catalog;

use Elastica\Result;
use Elastica\ResultSet;
use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\SearchElasticsearch\Plugin\ResultFormatter\AbstractElasticsearchResultFormatterPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * @method \SprykerCommunity\Client\SearchDebug\SearchDebugFactory getFactory()
 * @method \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface getClient()
 */
class SearchDebugResultFormatterPlugin extends AbstractElasticsearchResultFormatterPlugin
{
    /**
     * @var string
     */
    public const NAME = SearchDebugConfig::SEARCH_RESULT_KEY;

    /**
     * @var string
     */
    protected const PRODUCT_ID_KEY = 'id_product_abstract';

    /**
     * Elasticsearch hit key carrying the inline explanation, set when the query requested `explain`.
     *
     * @var string
     */
    protected const HIT_PARAM_EXPLANATION = '_explanation';

    /**
     * Elasticsearch hit key carrying the relevance score. Absent (JSON `null`) when the query is sorted
     * by something other than relevance, in which case Elasticsearch does not compute a score at all.
     *
     * @var string
     */
    protected const HIT_PARAM_SCORE = '_score';

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return string
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * {@inheritDoc}
     * - Returns the analyzer tokens of the search string (plus each distinct token's own character
     *   offset into the search string, for the SRP overlay's "trace this query token" link), plus
     *   per-product debug data keyed by abstract product id: `_score`, the matched query tokens with
     *   their score contributions (parsed from the Elasticsearch explain tree) and any other score
     *   contributions verbatim.
     * - Also returns the query's real field=>boost pairs, captured live off the query by
     *   `SearchDebugQueryExpanderPlugin`/`QueryFieldBoostReader` earlier in the same request.
     * - Returns an empty array unless search debug output was requested AND the current customer holds
     *   the search debug permission.
     *
     * @param \Elastica\ResultSet $searchResult
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, mixed>
     */
    protected function formatSearchResult(ResultSet $searchResult, array $requestParameters): array
    {
        $isSearchDebugEnabled = $this->getFactory()
            ->createSearchDebugAccessChecker()
            ->isSearchDebugEnabled($requestParameters);

        if (!$isSearchDebugEnabled) {
            return [];
        }

        $searchString = (string)($requestParameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_STRING] ?? '');
        $queryTokens = $this->getClient()->getSearchStringTokens($searchString);

        return [
            SearchDebugConfig::KEY_TOKENS => $queryTokens,
            SearchDebugConfig::KEY_TOKEN_OFFSETS => $this->getQueryTokenOffsets($searchString),
            SearchDebugConfig::KEY_PRODUCTS => $this->getProductDebugData($searchResult, $queryTokens),
            SearchDebugConfig::KEY_FIELD_BOOSTS => $this->getFactory()->createQueryFieldBoostReader()->getFieldBoosts(),
        ];
    }

    /**
     * One entry per DISTINCT query token (first occurrence wins for a token searched more than once —
     * see {@see SearchDebugConfig::KEY_TOKEN_OFFSETS} for why that is fine here), resolved through the
     * SEARCH-time analyzer: the raw query string was never indexed, only searched, so only that analyzer
     * actually processed it — unlike a product element's text, which the index-time analyzer produced.
     *
     * @param string $searchString
     *
     * @return array<string, array{startOffset: int, endOffset: int}>
     */
    protected function getQueryTokenOffsets(string $searchString): array
    {
        $tokenOffsets = [];

        foreach ($this->getClient()->getTextTokenOffsets($searchString, true) as $tokenOffset) {
            if (isset($tokenOffsets[$tokenOffset['token']])) {
                continue;
            }

            $tokenOffsets[$tokenOffset['token']] = [
                'startOffset' => $tokenOffset['startOffset'],
                'endOffset' => $tokenOffset['endOffset'],
            ];
        }

        return $tokenOffsets;
    }

    /**
     * @param \Elastica\ResultSet $searchResult
     * @param array<string> $queryTokens
     *
     * @return array<int|string, array<string, mixed>>
     */
    protected function getProductDebugData(ResultSet $searchResult, array $queryTokens): array
    {
        $explanationParser = $this->getFactory()->createExplanationParser();
        $debugDataExpanderPlugins = $this->getFactory()->getProductDebugDataExpanderPlugins();
        $debugDataByProductId = [];

        foreach ($searchResult->getResults() as $document) {
            $source = $document->getSource();

            if (!isset($source[PageIndexMap::SEARCH_RESULT_DATA][static::PRODUCT_ID_KEY])) {
                continue;
            }

            $idProductAbstract = $source[PageIndexMap::SEARCH_RESULT_DATA][static::PRODUCT_ID_KEY];

            $debugData = ['score' => $this->getScore($document)];

            if ($document->hasParam(static::HIT_PARAM_EXPLANATION)) {
                $debugData += $explanationParser->parse($document->getExplanation(), $queryTokens);
            }

            foreach ($debugDataExpanderPlugins as $debugDataExpanderPlugin) {
                $debugData = $debugDataExpanderPlugin->expandProductDebugData($debugData, $source);
            }

            $debugDataByProductId[$idProductAbstract] = $debugData;
        }

        return $debugDataByProductId;
    }

    /**
     * Returns `null` for an unscored hit rather than `Elastica\Result::getScore()`'s value: that method
     * delegates to `getParam()`, which returns an empty ARRAY (not `null`) for a hit Elasticsearch sent
     * with `"_score": null` — as it does for every non-relevance-sorted search.
     *
     * @param \Elastica\Result $document
     *
     * @return float|null
     */
    protected function getScore(Result $document): ?float
    {
        if (!$document->hasParam(static::HIT_PARAM_SCORE)) {
            return null;
        }

        return (float)$document->getScore();
    }
}
