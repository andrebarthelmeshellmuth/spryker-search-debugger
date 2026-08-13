<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Query;

use Codeception\Test\Unit;
use Elastica\Exception\InvalidException;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;
use Elastica\Query\MultiMatch;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReader;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Query
 * @group QueryFieldBoostReaderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 * @group NeedsSearch
 */
class QueryFieldBoostReaderTest extends Unit
{
    /**
     * `$fieldBoosts` is a STATIC property (deliberately, see the class's own docblock), so its value
     * survives across test methods within the same process. Every test method below therefore calls
     * `captureFromQuery()` itself before asserting on `getFieldBoosts()`, so no method depends on
     * execution order or on a previous method's leftover state.
     */
    public function testCaptureFromQueryReturnsTheRealFieldBoostPairsForATwoFieldMultiMatchQuery(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $searchQuery = $this->createSearchQuery($this->createMultiMatchQuery(['full-text', 'full-text-boosted^5']));

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert
        $this->assertSame(['full-text' => 1, 'full-text-boosted' => 5], $result);
        $this->assertSame(['full-text' => 1, 'full-text-boosted' => 5], $reader->getFieldBoosts());
    }

    /**
     * A field without a `^boost` suffix uses Elasticsearch's implicit boost of 1.
     */
    public function testCaptureFromQueryDefaultsAFieldWithNoCaretToBoostOne(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $searchQuery = $this->createSearchQuery($this->createMultiMatchQuery(['full-text']));

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert
        $this->assertSame(['full-text' => 1], $result);
        $this->assertSame(['full-text' => 1], $reader->getFieldBoosts());
    }

    /**
     * An empty search string produces a `MatchAll` top-level query — no `BoolQuery`, no `MultiMatch` — a
     * shape this reader must degrade to an empty array for rather than throw.
     */
    public function testCaptureFromQueryReturnsAnEmptyArrayForAMatchAllQuery(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $query = (new Query())->setQuery(new MatchAll());
        $searchQuery = $this->createSearchQuery($query);

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert
        $this->assertSame([], $result);
        $this->assertSame([], $reader->getFieldBoosts());
    }

    /**
     * A `BoolQuery` with a "must" clause that is something other than a `MultiMatch` (e.g. a plain `Term`
     * query) must also degrade to an empty array rather than throw.
     */
    public function testCaptureFromQueryReturnsAnEmptyArrayWhenTheMustClauseIsNotAMultiMatch(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $boolQuery = (new BoolQuery())->addMust(new MatchAll());
        $query = (new Query())->setQuery($boolQuery);
        $searchQuery = $this->createSearchQuery($query);

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert
        $this->assertSame([], $result);
        $this->assertSame([], $reader->getFieldBoosts());
    }

    /**
     * A query object that is not an `Elastica\Query` at all (some other project's `QueryInterface`
     * implementation) must also degrade to an empty array — `QueryInterface::getSearchQuery()` is typed
     * `mixed`, so this reader cannot assume its shape.
     */
    public function testCaptureFromQueryReturnsAnEmptyArrayWhenTheSearchQueryIsNotAnElasticaQuery(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $searchQuery = $this->createSearchQuery(new BoolQuery());

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert
        $this->assertSame([], $result);
        $this->assertSame([], $reader->getFieldBoosts());
    }

    /**
     * `getFieldBoosts()` returns an empty array when `captureFromQuery()` has not run in the current
     * request — exercised here by capturing a shape that itself resolves to an empty array, since the
     * underlying storage is a static property shared with other test methods (see this class's own
     * docblock above): capturing an empty result and reading it back is behaviorally identical to reading
     * it before any capture happened at all, and does not depend on test execution order.
     */
    public function testGetFieldBoostsReturnsAnEmptyArrayWhenNothingUsefulHasBeenCapturedYet(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $query = (new Query())->setQuery(new MatchAll());
        $reader->captureFromQuery($this->createSearchQuery($query));

        // Act
        $result = $reader->getFieldBoosts();

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * Fail-soft path: a `QueryInterface` implementation whose `getSearchQuery()` itself throws (e.g. a
     * plugin building its Elastica query lazily on first access) must not bubble the exception up through
     * `captureFromQuery()` — the SRP overlay's own score badges must still render even if the field-boost
     * capture, a purely informational side channel, fails.
     */
    public function testCaptureFromQueryReturnsAnEmptyArrayAndResetsFieldBoostsWhenGetSearchQueryThrows(): void
    {
        // Arrange
        $reader = new QueryFieldBoostReader();
        $reader->captureFromQuery($this->createSearchQuery($this->createMultiMatchQuery(['full-text'])));
        $searchQuery = $this->createThrowingSearchQuery();

        // Act
        $result = $reader->captureFromQuery($searchQuery);

        // Assert — a prior successful capture's value must not leak through a later failed one.
        $this->assertSame([], $result);
        $this->assertSame([], $reader->getFieldBoosts());
    }

    /**
     * @param array<string> $fields
     */
    protected function createMultiMatchQuery(array $fields): Query
    {
        $multiMatch = (new MultiMatch())->setFields($fields);
        $boolQuery = (new BoolQuery())->addMust($multiMatch);

        return (new Query())->setQuery($boolQuery);
    }

    protected function createThrowingSearchQuery(): QueryInterface
    {
        return new class implements QueryInterface {
            /**
             * @throws \Elastica\Exception\InvalidException
             *
             * @return mixed
             */
            public function getSearchQuery()
            {
                throw new InvalidException('search query could not be built');
            }
        };
    }

    /**
     * A minimal, inline `QueryInterface` implementation, mirroring the pattern
     * `SprykerCommunityTest\Client\SearchDebug\Plugin\Fixtures\BaseQueryPlugin` uses, but parameterized so
     * each test can drive `captureFromQuery()` with a different query shape.
     *
     * @param mixed $query
     */
    protected function createSearchQuery($query): QueryInterface
    {
        return new class ($query) implements QueryInterface {
            public function __construct(protected mixed $query)
            {
            }

            /**
             * @return mixed
             */
            public function getSearchQuery()
            {
                return $this->query;
            }
        };
    }
}
