<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Plugin;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use SprykerCommunity\Client\SearchDebug\SearchDebugFactory;
use SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugQueryExpanderPlugin;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessCheckerInterface;
use SprykerCommunityTest\Client\SearchDebug\Plugin\Fixtures\BaseQueryPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group Catalog
 * @group Plugin
 * @group Elasticsearch
 * @group QueryExpander
 * @group SearchDebugQueryExpanderPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class SearchDebugQueryExpanderPluginTest extends Unit
{
    /**
     * @return void
     */
    public function testExpandQueryEnablesExplainWhenSearchDebugIsEnabled(): void
    {
        // Arrange
        $queryExpanderPlugin = $this->createQueryExpanderPlugin(true);

        // Act
        $searchQuery = $queryExpanderPlugin->expandQuery(new BaseQueryPlugin());

        // Assert
        $this->assertTrue($searchQuery->getSearchQuery()->getParam('explain'));
    }

    /**
     * `explain` is real extra work on the search cluster, so an unpermitted caller must never reach it —
     * see SearchDebugAccessCheckerTest for the permission gate this delegates to.
     *
     * @return void
     */
    public function testExpandQueryLeavesTheQueryUntouchedWhenSearchDebugIsDisabled(): void
    {
        // Arrange
        $queryExpanderPlugin = $this->createQueryExpanderPlugin(false);
        $expectedQuery = (new Query())->setQuery(new BoolQuery());

        // Act
        $searchQuery = $queryExpanderPlugin->expandQuery(new BaseQueryPlugin());

        // Assert
        $this->assertFalse($searchQuery->getSearchQuery()->hasParam('explain'));
        $this->assertEquals($expectedQuery, $searchQuery->getSearchQuery());
    }

    /**
     * @param bool $isSearchDebugEnabled
     *
     * @return \SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugQueryExpanderPlugin
     */
    protected function createQueryExpanderPlugin(bool $isSearchDebugEnabled): SearchDebugQueryExpanderPlugin
    {
        $searchDebugAccessCheckerMock = $this->createMock(SearchDebugAccessCheckerInterface::class);
        $searchDebugAccessCheckerMock
            ->method('isSearchDebugEnabled')
            ->willReturn($isSearchDebugEnabled);

        $searchDebugFactoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchDebugAccessChecker'])
            ->getMock();
        $searchDebugFactoryMock
            ->method('createSearchDebugAccessChecker')
            ->willReturn($searchDebugAccessCheckerMock);

        $queryExpanderPlugin = new SearchDebugQueryExpanderPlugin();
        $queryExpanderPlugin->setFactory($searchDebugFactoryMock);

        return $queryExpanderPlugin;
    }
}
