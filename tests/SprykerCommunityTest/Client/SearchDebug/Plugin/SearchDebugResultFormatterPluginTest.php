<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Plugin;

use Codeception\Test\Unit;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;
use Elastica\ResultSet;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessCheckerInterface;
use SprykerCommunity\Client\SearchDebug\Explanation\Bm25BreakdownExtractor;
use SprykerCommunity\Client\SearchDebug\Explanation\CrossFieldsSynonymMatcher;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;
use SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugResultFormatterPlugin;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClient;
use SprykerCommunity\Client\SearchDebug\SearchDebugFactory;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group Catalog
 * @group Plugin
 * @group Elasticsearch
 * @group ResultFormatter
 * @group SearchDebugResultFormatterPluginTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class SearchDebugResultFormatterPluginTest extends Unit
{
    /**
     * @var int
     */
    protected const ID_PRODUCT_ABSTRACT = 42;

    /**
     * @return void
     */
    public function testFormatResultReturnsTheQueryTokensAndThePerProductDebugData(): void
    {
        // Arrange
        $resultFormatterPlugin = $this->createResultFormatterPlugin(true, ['cable']);
        $resultSet = $this->createResultSet([$this->createHit(20.7051, $this->createExplanation('full-text', 'cable', 19.84))]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertSame(['cable'], $result[SearchDebugConfig::KEY_TOKENS]);

        $productDebugData = $result[SearchDebugConfig::KEY_PRODUCTS][static::ID_PRODUCT_ABSTRACT];
        $this->assertSame(20.7051, $productDebugData['score']);
        $this->assertArrayHasKey('cable', $productDebugData[ExplanationParser::KEY_MATCHED_TOKENS]);
    }

    /**
     * The real field=>boost pairs are never computed here — they were already captured live off the
     * query by `SearchDebugQueryExpanderPlugin`/`QueryFieldBoostReader` earlier in the same request; this
     * plugin only has to read them back out via `QueryFieldBoostReader::getFieldBoosts()` and forward them
     * verbatim under {@see SearchDebugConfig::KEY_FIELD_BOOSTS}.
     *
     * @return void
     */
    public function testFormatResultReturnsTheFieldBoostsCapturedEarlierInTheRequest(): void
    {
        // Arrange
        $fieldBoosts = ['full-text' => 1, 'full-text-boosted' => 5];
        $resultFormatterPlugin = $this->createResultFormatterPlugin(true, ['cable'], $fieldBoosts);
        $resultSet = $this->createResultSet([$this->createHit(20.7051)]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertSame($fieldBoosts, $result[SearchDebugConfig::KEY_FIELD_BOOSTS]);
    }

    /**
     * A search sorted by anything other than relevance makes Elasticsearch skip scoring entirely and send
     * `"_score": null`. Elastica's `Result::getScore()` turns that into an empty ARRAY (its `getParam()`
     * null-coalesces to `[]`), which the template would then try to print — so the plugin must normalize it.
     *
     * @return void
     */
    public function testFormatResultReturnsANullScoreForAnUnscoredHitRatherThanAnArray(): void
    {
        // Arrange
        $resultFormatterPlugin = $this->createResultFormatterPlugin(true, ['cable']);
        $resultSet = $this->createResultSet([$this->createHit(null)]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertNull($result[SearchDebugConfig::KEY_PRODUCTS][static::ID_PRODUCT_ABSTRACT]['score']);
    }

    /**
     * @return void
     */
    public function testFormatResultReturnsTheQueryTokenOffsetsResolvedThroughTheSearchAnalyzer(): void
    {
        // Arrange
        $resultFormatterPlugin = $this->createResultFormatterPlugin(true, ['cable', 'tie'], [], [
            ['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5],
            ['token' => 'tie', 'startOffset' => 6, 'endOffset' => 9],
        ]);
        $resultSet = $this->createResultSet([$this->createHit(20.7051)]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertSame(
            [
                'cable' => ['startOffset' => 0, 'endOffset' => 5],
                'tie' => ['startOffset' => 6, 'endOffset' => 9],
            ],
            $result[SearchDebugConfig::KEY_TOKEN_OFFSETS],
        );
    }

    /**
     * A query token searched more than once (e.g. "cable cable tie") must still produce exactly ONE
     * offsets entry for it — the FIRST occurrence — mirroring the same simplification the token-color
     * assignment already makes for a repeated token.
     *
     * @return void
     */
    public function testFormatResultKeepsOnlyTheFirstOffsetForARepeatedQueryToken(): void
    {
        // Arrange
        $resultFormatterPlugin = $this->createResultFormatterPlugin(true, ['cable', 'cable'], [], [
            ['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5],
            ['token' => 'cable', 'startOffset' => 6, 'endOffset' => 11],
        ]);
        $resultSet = $this->createResultSet([$this->createHit(20.7051)]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertSame(
            ['cable' => ['startOffset' => 0, 'endOffset' => 5]],
            $result[SearchDebugConfig::KEY_TOKEN_OFFSETS],
        );
    }

    /**
     * @return void
     */
    public function testFormatResultReturnsNoDataWhenSearchDebugIsDisabled(): void
    {
        // Arrange
        $resultFormatterPlugin = $this->createResultFormatterPlugin(false, ['cable']);
        $resultSet = $this->createResultSet([$this->createHit(20.7051)]);

        // Act
        $result = $resultFormatterPlugin->formatResult($resultSet, $this->createRequestParameters());

        // Assert
        $this->assertSame([], $result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createRequestParameters(): array
    {
        return [
            SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG => true,
            SearchDebugConfig::REQUEST_PARAM_SEARCH_STRING => 'cable',
        ];
    }

    /**
     * @param float|null $score
     * @param array<string, mixed>|null $explanation
     *
     * @return \Elastica\Result
     */
    protected function createHit(?float $score, ?array $explanation = null): Result
    {
        $hit = [
            '_id' => (string)static::ID_PRODUCT_ABSTRACT,
            '_score' => $score,
            '_source' => [
                'search-result-data' => ['id_product_abstract' => static::ID_PRODUCT_ABSTRACT],
            ],
        ];

        if ($explanation !== null) {
            $hit['_explanation'] = $explanation;
        }

        return new Result($hit);
    }

    /**
     * @param string $field
     * @param string $term
     * @param float $value
     *
     * @return array<string, mixed>
     */
    protected function createExplanation(string $field, string $term, float $value): array
    {
        return [
            'value' => $value,
            'description' => sprintf('weight(%s:%s in 1) [PerFieldSimilarity], result of:', $field, $term),
            'details' => [],
        ];
    }

    /**
     * @param array<\Elastica\Result> $results
     *
     * @return \Elastica\ResultSet
     */
    protected function createResultSet(array $results): ResultSet
    {
        return new ResultSet(new Response('{}'), new Query(), $results);
    }

    /**
     * @param bool $isSearchDebugEnabled
     * @param array<string> $queryTokens
     * @param array<string, int> $fieldBoosts
     * @param array<array{token: string, startOffset: int, endOffset: int}> $queryTokenOffsets
     *
     * @return \SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugResultFormatterPlugin
     */
    protected function createResultFormatterPlugin(
        bool $isSearchDebugEnabled,
        array $queryTokens,
        array $fieldBoosts = [],
        array $queryTokenOffsets = [],
    ): SearchDebugResultFormatterPlugin {
        $searchDebugAccessCheckerMock = $this->createMock(SearchDebugAccessCheckerInterface::class);
        $searchDebugAccessCheckerMock
            ->method('isSearchDebugEnabled')
            ->willReturn($isSearchDebugEnabled);

        // The concrete client class is mocked (not the interface): AbstractPlugin::setClient() requires
        // an AbstractClient instance.
        $searchDebugClientMock = $this->createMock(SearchDebugClient::class);
        $searchDebugClientMock
            ->method('getSearchStringTokens')
            ->willReturn($queryTokens);
        $searchDebugClientMock
            ->method('getTextTokenOffsets')
            ->with($this->anything(), true)
            ->willReturn($queryTokenOffsets);

        $queryFieldBoostReaderMock = $this->createMock(QueryFieldBoostReaderInterface::class);
        $queryFieldBoostReaderMock
            ->method('getFieldBoosts')
            ->willReturn($fieldBoosts);

        $searchDebugFactoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchDebugAccessChecker', 'createExplanationParser', 'createQueryFieldBoostReader', 'getProductDebugDataExpanderPlugins'])
            ->getMock();
        $searchDebugFactoryMock
            ->method('createSearchDebugAccessChecker')
            ->willReturn($searchDebugAccessCheckerMock);
        $searchDebugFactoryMock
            ->method('createExplanationParser')
            ->willReturn(new ExplanationParser(new CrossFieldsSynonymMatcher(), new Bm25BreakdownExtractor()));
        $searchDebugFactoryMock
            ->method('createQueryFieldBoostReader')
            ->willReturn($queryFieldBoostReaderMock);
        $searchDebugFactoryMock
            ->method('getProductDebugDataExpanderPlugins')
            ->willReturn([]);

        $resultFormatterPlugin = new SearchDebugResultFormatterPlugin();
        $resultFormatterPlugin->setFactory($searchDebugFactoryMock);
        $resultFormatterPlugin->setClient($searchDebugClientMock);

        return $resultFormatterPlugin;
    }
}
