<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Analyzer;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisTreeBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Analyzer
 * @group AnalysisTreeBuilderTest
 * Add your own group annotations below this line
 * @group Portable
 */
class AnalysisTreeBuilderTest extends Unit
{
    /**
     * @param string $token
     * @param int $position
     *
     * @return array{token: string, startOffset: int, endOffset: int, position: int}
     */
    protected function token(string $token, int $position): array
    {
        return ['token' => $token, 'startOffset' => 0, 'endOffset' => strlen($token), 'position' => $position];
    }

    /**
     * @param string $operation
     * @param array<array{token: string, startOffset: int, endOffset: int, position: int}> $tokens
     *
     * @return array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}
     */
    protected function stage(string $operation, array $tokens): array
    {
        return [
            'operation' => $operation,
            'definition' => null,
            'componentKind' => null,
            'componentName' => null,
            'definitionTruncated' => false,
            'isStem' => false,
            'tokens' => $tokens,
        ];
    }

    public function testBuildReturnsEmptyTreeForNoStages(): void
    {
        // Act
        $tree = (new AnalysisTreeBuilder())->build([]);

        // Assert
        $this->assertSame(['stages' => [], 'edges' => []], $tree);
    }

    public function testBuildKeepsASingleStageWithNoEdges(): void
    {
        // Arrange
        $stages = [$this->stage('tokenizer: standard', [$this->token('chair', 0)])];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame(
            [['id' => '0:0', 'token' => 'chair', 'isRemoved' => false]],
            $tree['stages'][0]['nodes'],
        );
        $this->assertSame([], $tree['edges']);
    }

    public function testBuildLinksTwoStagesByMatchingPosition(): void
    {
        // Arrange — an unchanged token re-emitted at the same position by the next stage, the common
        // case for every filter that doesn't touch a given token.
        $stages = [
            $this->stage('tokenizer: standard', [$this->token('chair', 0)]),
            $this->stage('filter: lowercase', [$this->token('chair', 0)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame([['from' => '0:0', 'to' => '1:0']], $tree['edges']);
    }

    public function testBuildFansOutSynonymExpansionAtTheSamePosition(): void
    {
        // Arrange — a synonym filter expanding one token into two, both still claiming the original
        // token's own position (Elasticsearch's own convention for a same-slot alternative).
        $stages = [
            $this->stage('tokenizer: standard', [$this->token('chair', 0)]),
            $this->stage('filter: synonym', [$this->token('chair', 0), $this->token('seat', 0)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame(
            [
                ['from' => '0:0', 'to' => '1:0'],
                ['from' => '0:0', 'to' => '1:1'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildConnectsEveryChildToTheSoleSurvivorOfAPreviousStage(): void
    {
        // Arrange — a char filter's whole-text pseudo-token (always position 0, see
        // AnalysisTreeBuilder::buildLayers()'s own docblock) is the shared ancestor of every token the
        // tokenizer produces, regardless of their own real positions — confirmed live on a hyphenated
        // compound ("Bandscheiben-Drehstuhl") that only connected to the first word without this rule.
        $stages = [
            $this->stage('char filter: html_strip', [$this->token('bandscheiben-drehstuhl', 0)]),
            $this->stage('tokenizer: standard', [$this->token('bandscheiben', 0), $this->token('drehstuhl', 1)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame(
            [
                ['from' => '0:0', 'to' => '1:0'],
                ['from' => '0:0', 'to' => '1:1'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildMarksATokenWithNoSuccessorAsRemoved(): void
    {
        // Arrange — a stop-word filter dropping "und" outright: no token in the next stage shares its
        // position, so it must surface as a synthetic isRemoved node rather than silently vanishing.
        $stages = [
            $this->stage('tokenizer: standard', [$this->token('und', 0), $this->token('stuhl', 1)]),
            $this->stage('filter: german_stop_words', [$this->token('stuhl', 1)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert — "und" was removed from position 0, so its marker is spliced in BEFORE "stuhl"'s own
        // position-1 survivor, not appended after it (see insertNodeByPosition()'s own docblock).
        $this->assertSame(
            [
                ['id' => 'removed:0:0', 'token' => '∅', 'isRemoved' => true],
                ['id' => '1:0', 'token' => 'stuhl', 'isRemoved' => false],
            ],
            $tree['stages'][1]['nodes'],
        );
        $this->assertSame(
            [
                ['from' => '0:1', 'to' => '1:0'],
                ['from' => '0:0', 'to' => 'removed:0:0'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildNeverFlagsATokenInTheGenuineLastStage(): void
    {
        // Arrange — reaching the end of the pipeline alive is a real final token, not a removal, even
        // though (like every removed token) it has no outgoing edge of its own.
        $stages = [$this->stage('filter: lowercase', [$this->token('chair', 0)])];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame(
            [['id' => '0:0', 'token' => 'chair', 'isRemoved' => false]],
            $tree['stages'][0]['nodes'],
        );
    }

    public function testBuildInsertsARemovedMarkerInPositionOrderNotAppendedAfterEveryLargerSibling(): void
    {
        // Arrange — regression test for the malformed-description-fragment bug (`"><B>Verpackungseinheit"`):
        // the tokenizer's own short-lived "B" token (position 0) is dropped by a later min-length filter,
        // while its sibling "Verpackungseinheit" (position 1) survives and fans out widely (an edge-ngram
        // filter, simplified here to two ngram tokens for the test). The first cut of this insertion
        // appended the removed marker after every already-placed node in the row, dragging "B"'s own
        // ancestor chain across the whole diagram to sit past "Verpackungseinheit"'s unrelated fan-out —
        // the fix splices it in by position instead, so it must land BEFORE the wider sibling's tokens,
        // not after them.
        $stages = [
            $this->stage('tokenizer: standard', [$this->token('b', 0), $this->token('verpackungseinheit', 1)]),
            $this->stage('filter: min_length', [$this->token('verpackungseinheit', 1)]),
            $this->stage('filter: edge_ngram', [$this->token('ve', 1), $this->token('ver', 1)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert — the removed marker for "b" (position 0) must be spliced in BEFORE position 1's own
        // survivors, not appended after them.
        $this->assertSame(
            [
                ['id' => 'removed:0:0', 'token' => '∅', 'isRemoved' => true],
                ['id' => '1:0', 'token' => 'verpackungseinheit', 'isRemoved' => false],
            ],
            $tree['stages'][1]['nodes'],
        );
    }

    public function testBuildPrefersAnUnchangedPassThroughWhenMultipleCandidatesShareAPosition(): void
    {
        // Arrange — a decompounder emits two sub-words at the SAME position as the original (Elasticsearch's
        // own convention for a same-slot alternative, see testBuildFansOutSynonymExpansionAtTheSamePosition());
        // a stemmer then passes BOTH through unchanged (the common case for German). Position alone can't
        // tell which stem-stage token descends from which decompound-stage one — confirmed live: this used
        // to draw a fully-connected mesh (both stem tokens linked to both decompound tokens) instead of the
        // obviously-correct 1:1 pairing.
        $stages = [
            $this->stage('filter: decompound', [$this->token('brennenstuhlbrand', 0), $this->token('stuhl', 0)]),
            $this->stage('stem: german_stemmer', [$this->token('brennenstuhlbrand', 0), $this->token('stuhl', 0)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert — each unchanged token is paired with its own identically-spelled candidate, not every
        // candidate sharing its position.
        $this->assertSame(
            [
                ['from' => '0:0', 'to' => '1:0'],
                ['from' => '0:1', 'to' => '1:1'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildLinksEdgeNgramPrefixesOnlyToTheCandidateTheyWereCutFrom(): void
    {
        // Arrange — same same-position sibling pair as the pass-through test above, but this time
        // followed by an edge-ngram filter, which genuinely TRANSFORMS each source token into several
        // shorter prefixes rather than passing it through unchanged. Exact-text equality alone can't
        // resolve these (no ngram equals its own source token except the longest one) — confirmed live:
        // "st"/"stu"/"stuh" still meshed with BOTH "brennenstuhlbrand" and "stuhl" until the match was
        // widened from equality to a PREFIX relationship, since an edge-ngram filter's output is always a
        // prefix of whatever token it was cut from.
        $stages = [
            $this->stage('filter: decompound', [$this->token('brennenstuhlbrand', 0), $this->token('stuhl', 0)]),
            $this->stage('filter: edge_ngram', [
                $this->token('br', 0),
                $this->token('bre', 0),
                $this->token('brennenstuhlbrand', 0),
                $this->token('st', 0),
                $this->token('stu', 0),
                $this->token('stuhl', 0),
            ]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert — every "br"/"bre"/... ngram traces back to "brennenstuhlbrand" only, every
        // "st"/"stu"/... ngram traces back to "stuhl" only, never both.
        $this->assertSame(
            [
                ['from' => '0:0', 'to' => '1:0'],
                ['from' => '0:0', 'to' => '1:1'],
                ['from' => '0:0', 'to' => '1:2'],
                ['from' => '0:1', 'to' => '1:3'],
                ['from' => '0:1', 'to' => '1:4'],
                ['from' => '0:1', 'to' => '1:5'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildFallsBackToEveryCandidateWhenNoneIsPrefixRelatedAtASharedPosition(): void
    {
        // Arrange — genuinely ambiguous: two same-position candidates in the previous stage, and the
        // current stage's own token ("kwik", a synonym-style replacement) shares no prefix relationship
        // with either — not equal to, not a prefix of, and not prefixed by, either candidate. No signal
        // exists to disambiguate, so every candidate at that position is linked — the same,
        // still-imprecise fallback this class's own docblock already documents as an accepted limitation.
        $stages = [
            $this->stage('filter: decompound', [$this->token('brennenstuhlbrand', 0), $this->token('stuhl', 0)]),
            $this->stage('filter: synonym', [$this->token('kwik', 0)]),
        ];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertSame(
            [
                ['from' => '0:0', 'to' => '1:0'],
                ['from' => '0:1', 'to' => '1:0'],
            ],
            $tree['edges'],
        );
    }

    public function testBuildStripsPositionFromThePublicNodeShape(): void
    {
        // Arrange
        $stages = [$this->stage('tokenizer: standard', [$this->token('chair', 0)])];

        // Act
        $tree = (new AnalysisTreeBuilder())->build($stages);

        // Assert
        $this->assertArrayNotHasKey('position', $tree['stages'][0]['nodes'][0]);
    }
}
