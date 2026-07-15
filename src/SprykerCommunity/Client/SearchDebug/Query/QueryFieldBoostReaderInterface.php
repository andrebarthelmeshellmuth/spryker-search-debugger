<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Query;

use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;

interface QueryFieldBoostReaderInterface
{
    /**
     * Specification:
     * - Reads the real field+boost pairs off the given search query's `multi_match` clause (e.g.
     *   `['full-text' => 1, 'full-text-boosted' => 5]`) — whatever fields and boost values that query
     *   actually uses, no field name or boost value is assumed or hardcoded.
     * - Stores the result for later retrieval via `getFieldBoosts()` within the same request: the query
     *   build and the result formatting run as one sequential pipeline in Spryker's search client, so a
     *   value captured here during query expansion is available by the time results are formatted.
     * - Returns (and stores) an empty array for any query shape other than a `multi_match` inside a bool
     *   "must" clause (e.g. an empty search string's `MatchAll` query, or a project's differently-shaped
     *   query) — never throws; this is informational display data, never load-bearing for the real search.
     *
     * @api
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     *
     * @return array<string, int>
     */
    public function captureFromQuery(QueryInterface $searchQuery): array;

    /**
     * Specification:
     * - Returns whatever `captureFromQuery()` last captured within the current request.
     * - Returns an empty array if `captureFromQuery()` has not run in the current request.
     *
     * @api
     *
     * @return array<string, int>
     */
    public function getFieldBoosts(): array;
}
