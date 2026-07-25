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
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighter;

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
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: lowercase',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: fulltext_index_ngram_filter',
                'definition' => 'edge_ngram (min_gram: 2, max_gram: 20)',
                'componentKind' => 'filter',
                'componentName' => 'fulltext_index_ngram_filter',
                'definitionTruncated' => false,
                'tokens' => [
                    ['token' => 'öl', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölp', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act
        $path = $resolver->resolve('Ölpapier', 'öl', 0, 8);

        // Assert
        $this->assertSame(
            [
                ['text' => 'Ölpapier', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                ['text' => 'ölpapier', 'operation' => 'filter: lowercase', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                ['text' => 'öl', 'operation' => 'filter: fulltext_index_ngram_filter', 'definition' => 'edge_ngram (min_gram: 2, max_gram: 20)', 'componentKind' => 'filter', 'componentName' => 'fulltext_index_ngram_filter', 'definitionTruncated' => false, 'highlightedHtml' => null],
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
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: lowercase',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]],
            ],
            [
                'operation' => 'filter: fulltext_index_ngram_filter',
                'definition' => 'edge_ngram (min_gram: 2, max_gram: 20)',
                'componentKind' => 'filter',
                'componentName' => 'fulltext_index_ngram_filter',
                'definitionTruncated' => false,
                'tokens' => [
                    ['token' => 'öl', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölp', 'startOffset' => 0, 'endOffset' => 8],
                    ['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act — same text and offsets as the test above, but asking about the LAST (longest) sibling.
        $path = $resolver->resolve('Ölpapier', 'ölpapier', 0, 8);

        // Assert
        $this->assertSame(
            [
                ['text' => 'Ölpapier', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                ['text' => 'ölpapier', 'operation' => 'filter: lowercase', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                ['text' => 'ölpapier', 'operation' => 'filter: fulltext_index_ngram_filter', 'definition' => 'edge_ngram (min_gram: 2, max_gram: 20)', 'componentKind' => 'filter', 'componentName' => 'fulltext_index_ngram_filter', 'definitionTruncated' => false, 'highlightedHtml' => null],
            ],
            $path,
        );
    }

    /**
     * Regression test: confirmed live against a real product description matching this shop's
     * "switch, button" synonym rule. A synonym filter INJECTS an additional token at the EXACT SAME
     * offset as the word that triggered it — unlike edge-ngram siblings (which are all substrings of
     * each other, so the tightest-span heuristic naturally picks the right one), a synonym-injected
     * token shares an IDENTICAL span with a sibling that has completely UNRELATED text. Offset
     * containment alone can't disambiguate that; only a same-text preference can — without it, resolving
     * "button" would silently walk back through "switch" at every pass-through stage after the
     * injection and only reveal "button" again at the very last stage, attributing the transformation to
     * the wrong filter entirely.
     *
     * @return void
     */
    public function testResolveFollowsTheInjectedSynonymNotTheOriginalWordItWasInjectedAlongside(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'Switch', 'startOffset' => 0, 'endOffset' => 6]],
            ],
            [
                'operation' => 'filter: lowercase',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'switch', 'startOffset' => 0, 'endOffset' => 6]],
            ],
            [
                'operation' => 'filter: fulltext_synonyms',
                'definition' => 'synonym (synonyms: switch, button => switch, button)',
                'componentKind' => 'filter',
                'componentName' => 'fulltext_synonyms',
                'definitionTruncated' => false,
                // Both siblings span the SAME offset — "button" was injected, not derived by
                // transforming "switch"'s text.
                'tokens' => [
                    ['token' => 'switch', 'startOffset' => 0, 'endOffset' => 6],
                    ['token' => 'button', 'startOffset' => 0, 'endOffset' => 6],
                ],
            ],
            [
                // A pure pass-through stage — both siblings survive unchanged, still at the same offset.
                'operation' => 'filter: fulltext_min_length',
                'definition' => 'length (min: 2)',
                'componentKind' => 'filter',
                'componentName' => 'fulltext_min_length',
                'definitionTruncated' => false,
                'tokens' => [
                    ['token' => 'switch', 'startOffset' => 0, 'endOffset' => 6],
                    ['token' => 'button', 'startOffset' => 0, 'endOffset' => 6],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act — asking about "button", the injected sibling, not "switch", the original word.
        $path = $resolver->resolve('Switch', 'button', 0, 6);

        // Assert — "button" stays "button" through the pass-through stage; only the synonym stage
        // itself shows the transformation from "switch".
        $this->assertSame(
            [
                ['text' => 'Switch', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                ['text' => 'switch', 'operation' => 'filter: lowercase', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
                [
                    'text' => 'button',
                    'operation' => 'filter: fulltext_synonyms',
                    'definition' => 'synonym (synonyms: switch, button => switch, button)',
                    'componentKind' => 'filter',
                    'componentName' => 'fulltext_synonyms',
                    'definitionTruncated' => false,
                    'highlightedHtml' => null,
                ],
                [
                    'text' => 'button',
                    'operation' => 'filter: fulltext_min_length',
                    'definition' => 'length (min: 2)',
                    'componentKind' => 'filter',
                    'componentName' => 'fulltext_min_length',
                    'definitionTruncated' => false,
                    'highlightedHtml' => null,
                ],
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
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'haustuere', 'startOffset' => 0, 'endOffset' => 9]],
            ],
            [
                'operation' => 'filter: decompound',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [
                    ['token' => 'haus', 'startOffset' => 0, 'endOffset' => 4],
                    ['token' => 'tuere', 'startOffset' => 4, 'endOffset' => 9],
                ],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act
        $path = $resolver->resolve('haustuere', 'tuere', 4, 9);

        // Assert — only the "tuere" lineage appears; "haus" is never part of the result. The origin also
        // carries a highlight: "tuere" is a genuine, offset-exact sub-span of "haustuere" itself, so
        // addOriginHighlight()'s validation passes.
        $this->assertSame(
            [
                ['text' => 'haustuere', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => 'haus<mark class="search-debug-highlight">tuere</mark>'],
                ['text' => 'tuere', 'operation' => 'filter: decompound', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ],
            $path,
        );
    }

    /**
     * When a stage's config was too long to preview in full (see `ComponentDefinitionFormatter`'s
     * `truncated` flag), the resulting path entry must carry `componentKind`/`componentName` alongside
     * `definitionTruncated: true` — everything a "view full definition" link needs to re-fetch the
     * untruncated config via `SearchDebugClientInterface::getComponentConfig()`.
     *
     * @return void
     */
    public function testResolveCarriesComponentKindAndNameWhenTheDefinitionWasTruncated(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([
            [
                'operation' => 'tokenizer: standard',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'cables', 'startOffset' => 0, 'endOffset' => 6]],
            ],
            [
                'operation' => 'filter: my_stop',
                'definition' => 'stop (stopwords: a, an, the, and, or, … (7 total))',
                'componentKind' => 'filter',
                'componentName' => 'my_stop',
                'definitionTruncated' => true,
                'tokens' => [['token' => 'cables', 'startOffset' => 0, 'endOffset' => 6]],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act
        $path = $resolver->resolve('cables', 'cables', 0, 6);

        // Assert
        $this->assertSame(
            [
                ['text' => 'cables', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => '<mark class="search-debug-highlight">cables</mark>'],
                [
                    'text' => 'cables',
                    'operation' => 'filter: my_stop',
                    'definition' => 'stop (stopwords: a, an, the, and, or, … (7 total))',
                    'componentKind' => 'filter',
                    'componentName' => 'my_stop',
                    'definitionTruncated' => true,
                    'highlightedHtml' => null,
                ],
            ],
            $path,
        );
    }

    /**
     * `$useSearchAnalyzer` is forwarded verbatim to the client — this resolver has no analyzer choice of
     * its own to make, it only threads the caller's choice through to whichever `_analyze` call actually
     * produces the stages.
     *
     * @return void
     */
    public function testResolveForwardsTheSearchAnalyzerFlagToTheClient(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->expects($this->once())
            ->method('getTextAnalysisStages')
            ->with('cable', true)
            ->willReturn([
                [
                    'operation' => 'tokenizer: standard',
                    'definition' => null,
                    'componentKind' => null,
                    'componentName' => null,
                    'definitionTruncated' => false,
                    'tokens' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]],
                ],
            ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act
        $resolver->resolve('cable', 'cable', 0, 5, true);
    }

    /**
     * @return void
     */
    public function testResolveReturnsNullWhenTheClientHasNoStagesAtAll(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextAnalysisStages')->willReturn([]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

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
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

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
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                // Deliberately does NOT contain offset [0,5) — simulates a broken/inconsistent stage.
                'tokens' => [['token' => 'unrelated', 'startOffset' => 20, 'endOffset' => 29]],
            ],
            [
                'operation' => 'filter: lowercase',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]],
            ],
        ]);

        $resolver = new AnalysisPathResolver($searchDebugClientMock, new TokenHighlighter());

        // Act
        $path = $resolver->resolve('Cable', 'cable', 0, 5);

        // Assert — stops at the point it can no longer find an ancestor, rather than throwing.
        $this->assertSame(
            [
                ['text' => 'cable', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ],
            $path,
        );
    }
}
