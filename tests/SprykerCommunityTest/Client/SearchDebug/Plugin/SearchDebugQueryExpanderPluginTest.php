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
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessCheckerInterface;
use SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugQueryExpanderPlugin;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugFactory;
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
    public function testExpandQueryEnablesExplainWhenSearchDebugIsEnabled(): void
    {
        // Arrange
        $queryFieldBoostReaderMock = $this->createMock(QueryFieldBoostReaderInterface::class);
        $queryFieldBoostReaderMock
            ->expects($this->once())
            ->method('captureFromQuery')
            ->with($this->isInstanceOf(BaseQueryPlugin::class))
            ->willReturn(['full-text-boosted' => 5]);

        $queryExpanderPlugin = $this->createQueryExpanderPlugin(true, $queryFieldBoostReaderMock);

        // Act
        $searchQuery = $queryExpanderPlugin->expandQuery(new BaseQueryPlugin());

        // Assert
        $this->assertTrue($searchQuery->getSearchQuery()->getParam('explain'));
    }

    /**
     * `explain` is real extra work on the search cluster, so an unpermitted caller must never reach it —
     * see SearchDebugAccessCheckerTest for the permission gate this delegates to. The same gate must keep
     * an unpermitted caller from triggering `QueryFieldBoostReader::captureFromQuery()` too: it is
     * harmless in itself, but there is no reason to do the extra work when the result will never be shown.
     */
    public function testExpandQueryLeavesTheQueryUntouchedWhenSearchDebugIsDisabled(): void
    {
        // Arrange
        $queryExpanderPlugin = $this->createQueryExpanderPlugin(false, null, $this->never());
        $expectedQuery = (new Query())->setQuery(new BoolQuery());

        // Act
        $searchQuery = $queryExpanderPlugin->expandQuery(new BaseQueryPlugin());

        // Assert
        $this->assertFalse($searchQuery->getSearchQuery()->hasParam('explain'));
        $this->assertEquals($expectedQuery, $searchQuery->getSearchQuery());
    }

    /**
     * @param bool $isSearchDebugEnabled
     * @param \SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface|null $queryFieldBoostReaderMock
     * @param \PHPUnit\Framework\MockObject\Rule\InvocationOrder|null $createQueryFieldBoostReaderInvocationRule
     */
    protected function createQueryExpanderPlugin(
        bool $isSearchDebugEnabled,
        ?QueryFieldBoostReaderInterface $queryFieldBoostReaderMock = null,
        ?InvocationOrder $createQueryFieldBoostReaderInvocationRule = null,
    ): SearchDebugQueryExpanderPlugin {
        $searchDebugAccessCheckerMock = $this->createMock(SearchDebugAccessCheckerInterface::class);
        $searchDebugAccessCheckerMock
            ->method('isSearchDebugEnabled')
            ->willReturn($isSearchDebugEnabled);

        $searchDebugFactoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchDebugAccessChecker', 'createQueryFieldBoostReader'])
            ->getMock();
        $searchDebugFactoryMock
            ->method('createSearchDebugAccessChecker')
            ->willReturn($searchDebugAccessCheckerMock);
        $searchDebugFactoryMock
            ->expects($createQueryFieldBoostReaderInvocationRule ?? $this->any())
            ->method('createQueryFieldBoostReader')
            ->willReturn($queryFieldBoostReaderMock ?? $this->createMock(QueryFieldBoostReaderInterface::class));

        $queryExpanderPlugin = new SearchDebugQueryExpanderPlugin();
        $queryExpanderPlugin->setFactory($searchDebugFactoryMock);

        return $queryExpanderPlugin;
    }
}
