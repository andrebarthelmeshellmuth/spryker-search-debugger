<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Plugin\Fixtures;

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;

/**
 * A minimal real query plugin used as the input of the query expander tests, mirroring
 * \SprykerTest\Client\SearchElasticsearch\Plugin\Fixtures\BaseQueryPlugin from the core test suite.
 */
class BaseQueryPlugin implements QueryInterface
{
    /**
     * @var \Elastica\Query
     */
    protected Query $query;

    public function __construct()
    {
        $this->query = (new Query())->setQuery(new BoolQuery());
    }

    public function getSearchQuery(): Query
    {
        return $this->query;
    }
}
