<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Resolver;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group AnalysisPathResolverTest
 * Add your own group annotations below this line
 */
class AnalysisPathResolverTest extends Unit
{
    /**
     * The exact stage breakdown confirmed live against a real basic shop's index-time analyzer for
     * "Ölpapier" (tokenizer -> lowercase -> edge-ngram filter) — grounding this unit test in real data
     * rather than an invented fixture.
     *
     * @return void
     */
    public function testResolveWalksBackwardThroughEveryStageToBuildTheFullPath(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'tokens' => [['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: lowercase',
                'tokens' => [['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: fulltext_index_ngram_filter',
                'tokens' => [
                    ['token' => 'öl', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölp', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act
        $path = $resolver->resolve('Ölpapier', 'öl', 0, 8);

        // Assert
        $this->assertSame(
            [
                ['text' => 'Ölpapier', 'operation' => null],
                ['text' => 'ölpapier', 'operation' => 'filter: lowercase'],
                ['text' => 'öl', 'operation' => 'filter: fulltext_index_ngram_filter'],
            ],
            $path,
        );
    }

    /**
     * Regression test: an edge-ngram filter reports every prefix of a word at the SAME whole-word
     * offset (see the fixture in the test above — "öl", "ölp", "ölpapier" all span [0,8)), so matching
     * the starting token by offset ALONE picks whichever one happens to come first in the array, not
     * necessarily the one actually being asked about. Requesting the LONGEST sibling here (not the
     * first array entry) would return "öl"'s path if `resolve()` silently ignored $token.
     *
     * @return void
     */
    public function testResolveDisambiguatesBetweenSiblingsThatShareTheExactSameOffset(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'tokens' => [['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: lowercase',
                'tokens' => [['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: fulltext_index_ngram_filter',
                'tokens' => [
                    ['token' => 'öl', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölp', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act — same text and offsets as the test above, but asking about the LAST (longest) sibling.
        $path = $resolver->resolve('Ölpapier', 'ölpapier', 0, 8);

        // Assert
        $this->assertSame(
            [
                ['text' => 'Ölpapier', 'operation' => null],
                ['text' => 'ölpapier', 'operation' => 'filter: lowercase'],
                ['text' => 'ölpapier', 'operation' => 'filter: fulltext_index_ngram_filter'],
            ],
            $path,
        );
    }

    /**
     * A filter fanning ONE token into several (ngram, decompounding, synonyms, ...) must never turn the
     * result into a tree: only the ONE sibling whose range contains the target token is followed — the
     * others are never even inspected for their own ancestry. Simulated here with a decompounding-style
     * filter splitting "haustuere" into two narrower tokens, tracing back to "tuere" only.
     *
     * @return void
     */
    public function testResolveFollowsOnlyTheOneLineageWhenAStageFansOutIntoMultipleTokens(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'tokens' => [['token' => 'haustuere', 'startOffset' => 0, 'endOffset' => 9]],
            ],
            [
                'operation' => 'filter: decompound',
                'tokens' => [
                    ['token' => 'haus', 'startOffset' => 0, 'endOffset' => 4],
                    ['token' => 'tuere', 'startOffset' => 4, 'endOffset' => 9],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act
        $path = $resolver->resolve('haustuere', 'tuere', 4, 9);

        // Assert — only the "tuere" lineage appears; "haus" is never part of the result.
        $this->assertSame(
            [
                ['text' => 'haustuere', 'operation' => null],
                ['text' => 'tuere', 'operation' => 'filter: decompound'],
            ],
            $path,
        );
    }

    /**
     * @return void
     */
    public function testResolveReturnsNullWhenTheClientHasNoStagesAtAll(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act
        $path = $resolver->resolve('anything', 'any', 0, 3);

        // Assert
        $this->assertNull($path);
    }

    /**
     * @return void
     */
    public function testResolveReturnsNullWhenTheTargetOffsetIsNotInTheLastStage(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'tokens' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act — offsets don't match the one real token at all (stale/re-indexed document scenario).
        $path = $resolver->resolve('cable', 'cable', 10, 15);

        // Assert
        $this->assertNull($path);
    }

    /**
     * If an earlier stage genuinely has no token containing the current one (shouldn't happen — offsets
     * are a Lucene invariant across stages — but this is defensive code), the walk stops there instead
     * of crashing: a partial path is still useful diagnostic information.
     *
     * @return void
     */
    public function testResolveReturnsAPartialPathWhenAnEarlierStageHasNoContainingToken(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                // Deliberately does NOT contain offset [0,5) — simulates a broken/inconsistent stage.
                'tokens' => [['token' => 'unrelated', 'startOffset' => 20, 'endOffset' => 29]],
            ],
            [
                'operation' => 'filter: lowercase',
                'tokens' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock);

        // Act
        $path = $resolver->resolve('Cable', 'cable', 0, 5);

        // Assert — stops at the point it can no longer find an ancestor, rather than throwing.
        $this->assertSame(
            [
                ['text' => 'cable', 'operation' => null],
            ],
            $path,
        );
    }
}
