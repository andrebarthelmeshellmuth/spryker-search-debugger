<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Analyzer;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\StoreTransfer;
use Spryker\Client\Store\StoreClientInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugDependencyProvider;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch.
 *
 * This is deliberately not mocked. The whole point of the analyzer is that the tokens it reports are the
 * ones Elasticsearch really produces for a query, and the two things that can silently be wrong — the
 * resolved index name and the analyzer name (`fulltext_search_analyzer`, defined in
 * `src/Pyz/Shared/Search/Schema/page.json`) — are exactly the things a mocked Elastica client would
 * happily accept while returning nothing useful. A wrong analyzer name here does not throw: the analyzer
 * swallows Elasticsearch errors and returns an empty token list, so the debug headline would just quietly
 * disappear. Only a real round-trip catches that.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchElasticsearch
 * @group Analyzer
 * @group SearchStringAnalyzerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class SearchStringAnalyzerTest extends Unit
{
    /**
     * @var string
     */
    protected const STORE_NAME = 'DE';

    /**
     * Only the store lookup is stubbed — it otherwise reads the store list from Redis, which is infra this
     * test has no interest in. The index name is still built by the real IndexNameResolver (prefix + store +
     * source identifier) and the `_analyze` call still goes to a real Elasticsearch, which is the point.
     *
     * @return void
     */
    protected function _before(): void
    {
        $storeClientMock = $this->createMock(StoreClientInterface::class);
        $storeClientMock
            ->method('getCurrentStore')
            ->willReturn((new StoreTransfer())->setName(static::STORE_NAME));

        $this->tester->setDependency(SearchDebugDependencyProvider::CLIENT_STORE, $storeClientMock);
    }

    /**
     * The query-time analyzer is `standard` tokenizer + `lowercase`, with no edge-ngram (that one is
     * index-time only) and no stemmer — so a hyphenated compound splits into whole lowercased words.
     *
     * @return void
     */
    public function testGetSearchStringTokensReturnsTheQueryTimeAnalyzerTokens(): void
    {
        // Act
        $tokens = $this->tester->getSearchDebugClient()->getSearchStringTokens('Eisen-Hammer');

        // Assert
        $this->assertSame(['eisen', 'hammer'], $tokens);
    }

    /**
     * @return void
     */
    public function testGetSearchStringTokensLowercasesASingleTerm(): void
    {
        // Act
        $tokens = $this->tester->getSearchDebugClient()->getSearchStringTokens('CABLE');

        // Assert
        $this->assertSame(['cable'], $tokens);
    }

    /**
     * An empty search string must not cause a request to Elasticsearch at all.
     *
     * @return void
     */
    public function testGetSearchStringTokensReturnsAnEmptyListForAnEmptySearchString(): void
    {
        // Act
        $tokens = $this->tester->getSearchDebugClient()->getSearchStringTokens('');

        // Assert
        $this->assertSame([], $tokens);
    }

    /**
     * `fulltext_index_analyzer` is a custom analyzer (tokenizer + filter chain, not a built-in named
     * one) — Elasticsearch's `explain` response therefore nests tokens under `tokenfilters[]`, not under
     * a top-level `analyzer` key. Only a real round-trip catches a wrong assumption about that shape.
     *
     * This is deliberately the INDEX-time analyzer, unlike `getSearchStringTokens()` above — it includes
     * the edge-ngram filter, so a word explodes into every 2-to-20-char prefix, each one reported at the
     * OFFSET OF THE WHOLE WORD it came from (not the prefix's own span) — e.g. "ei" here is
     * `startOffset: 0, endOffset: 5`, the same span as "eisen", not `endOffset: 2`. That's intentional and
     * relied upon downstream (`TokenHighlighter`): highlighting a short query token like "öl" that only
     * matched via a prefix still highlights the whole word ("Ölpapier") it was found in, not an
     * out-of-context 2-character fragment.
     *
     * @return void
     */
    public function testGetTextTokenOffsetsReturnsTokensWithOffsetsIntoTheOriginalText(): void
    {
        // Act
        $tokenOffsets = $this->tester->getSearchDebugClient()->getTextTokenOffsets('Eisen-Hammer');

        // Assert
        $this->assertSame(
            [
                ['token' => 'ei', 'startOffset' => 0, 'endOffset' => 5],
                ['token' => 'eis', 'startOffset' => 0, 'endOffset' => 5],
                ['token' => 'eise', 'startOffset' => 0, 'endOffset' => 5],
                ['token' => 'eisen', 'startOffset' => 0, 'endOffset' => 5],
                ['token' => 'ha', 'startOffset' => 6, 'endOffset' => 12],
                ['token' => 'ham', 'startOffset' => 6, 'endOffset' => 12],
                ['token' => 'hamm', 'startOffset' => 6, 'endOffset' => 12],
                ['token' => 'hamme', 'startOffset' => 6, 'endOffset' => 12],
                ['token' => 'hammer', 'startOffset' => 6, 'endOffset' => 12],
            ],
            $tokenOffsets,
        );
    }

    /**
     * @return void
     */
    public function testGetTextTokenOffsetsReturnsAnEmptyListForEmptyText(): void
    {
        // Act
        $tokenOffsets = $this->tester->getSearchDebugClient()->getTextTokenOffsets('');

        // Assert
        $this->assertSame([], $tokenOffsets);
    }
}
