<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Plugin\Catalog;

use Elastica\Query;
use Spryker\Client\Kernel\AbstractPlugin;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryExpanderPluginInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;

/**
 * @method \SprykerCommunity\Client\SearchDebug\SearchDebugFactory getFactory()
 */
class SearchDebugQueryExpanderPlugin extends AbstractPlugin implements QueryExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     * - Enables Elasticsearch inline `explain` on the search query for a customer who requested search
     *   debug output AND holds the search debug permission.
     * - Leaves the query untouched otherwise — `explain` is real extra work on the search cluster, so it
     *   must never be reachable by an unpermitted caller.
     *
     * @api
     *
     * @param \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface $searchQuery
     * @param array<string, mixed> $requestParameters
     *
     * @return \Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface
     */
    public function expandQuery(QueryInterface $searchQuery, array $requestParameters = [])
    {
        $isSearchDebugEnabled = $this->getFactory()
            ->createSearchDebugAccessChecker()
            ->isSearchDebugEnabled($requestParameters);

        if (!$isSearchDebugEnabled) {
            return $searchQuery;
        }

        $query = $searchQuery->getSearchQuery();

        if ($query instanceof Query) {
            $query->setExplain(true);
        }

        return $searchQuery;
    }
}
