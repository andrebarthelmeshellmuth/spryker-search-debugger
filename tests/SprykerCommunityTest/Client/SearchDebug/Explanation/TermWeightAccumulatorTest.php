<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Explanation;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;
use SprykerCommunity\Client\SearchDebug\Explanation\TermWeightAccumulator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Explanation
 * @group TermWeightAccumulatorTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 * @group Portable
 */
class TermWeightAccumulatorTest extends Unit
{
    /**
     * A single field weight becomes both the total and the "primary" field.
     */
    public function testAddTermRecordsASingleFieldWeightAsTheTotal(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addTerm('cable', 'full-text', 5.5, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertSame(5.5, $terms['cable']['total']);
        $this->assertSame('full-text', $terms['cable']['field']);
    }

    /**
     * MAX mode (dis_max): only the best-scoring field contributes to the total, and it becomes the
     * "primary" field.
     */
    public function testAddTermTakesTheMaxAcrossFieldsUnderMaxMode(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addTerm('cable', 'full-text', 5.98, ExplanationParser::COMBINE_MODE_MAX);
        $accumulator->addTerm('cable', 'full-text-boosted', 19.84, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertSame(19.84, $terms['cable']['total']);
        $this->assertSame('full-text-boosted', $terms['cable']['field']);
    }

    /**
     * SUM mode (bool-should): every matching field genuinely adds to `_score`, so the total is the sum —
     * but "field" still names the single largest individual contributor, not a claim it is the only source.
     */
    public function testAddTermSumsAcrossFieldsUnderSumMode(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addTerm('cable', 'full-text', 5.0, ExplanationParser::COMBINE_MODE_SUM);
        $accumulator->addTerm('cable', 'full-text-boosted', 10.0, ExplanationParser::COMBINE_MODE_SUM);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertSame(15.0, $terms['cable']['total']);
        $this->assertSame('full-text-boosted', $terms['cable']['field']);
    }

    /**
     * A repeated call for the SAME field (not a different one) still needs to combine correctly — MAX of
     * the same field across two calls, not double-counted as if it were sum.
     */
    public function testAddTermCombinesRepeatedCallsForTheSameFieldUnderMaxMode(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addTerm('cable', 'full-text', 3.0, ExplanationParser::COMBINE_MODE_MAX);
        $accumulator->addTerm('cable', 'full-text', 7.0, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertSame(7.0, $terms['cable']['total']);
    }

    /**
     * Only the winning field's BM25 breakdown is ever carried — a losing field's breakdown is stored
     * internally but never surfaces as "the" breakdown once a better field takes over.
     */
    public function testAddTermOnlyExposesTheWinningFieldsBreakdown(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();
        $losingBreakdown = ['boost' => 1.0, 'idf' => ['value' => 1.0, 'n' => 1.0, 'capitalN' => 1.0], 'tf' => ['value' => 1.0, 'freq' => 1.0, 'k1' => 1.0, 'b' => 1.0, 'dl' => 1.0, 'avgdl' => 1.0]];
        $winningBreakdown = ['boost' => 2.0, 'idf' => ['value' => 2.0, 'n' => 2.0, 'capitalN' => 2.0], 'tf' => ['value' => 2.0, 'freq' => 2.0, 'k1' => 2.0, 'b' => 2.0, 'dl' => 2.0, 'avgdl' => 2.0]];

        // Act
        $accumulator->addTerm('cable', 'full-text', 5.0, ExplanationParser::COMBINE_MODE_MAX, $losingBreakdown);
        $accumulator->addTerm('cable', 'full-text-boosted', 10.0, ExplanationParser::COMBINE_MODE_MAX, $winningBreakdown);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertSame($winningBreakdown, $terms['cable']['fieldBreakdowns']['full-text-boosted']);
        // The losing field's own breakdown is still stored per-field (never discarded outright)...
        $this->assertSame($losingBreakdown, $terms['cable']['fieldBreakdowns']['full-text']);
        // ...but only the winning field is what a caller is expected to read as "the" breakdown.
        $this->assertSame('full-text-boosted', $terms['cable']['field']);
    }

    public function testAddSynonymCombinesAllPairsIntoASortedJoinedKey(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addSynonym('full-text:switch full-text:button', 5.428672, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertArrayHasKey('button, switch', $terms);
        $this->assertSame(5.428672, $terms['button, switch']['total']);
        $this->assertSame('full-text', $terms['button, switch']['field']);
    }

    /**
     * A synonym rule can list any number of equivalent words — must not assume exactly 2.
     */
    public function testAddSynonymHandlesMoreThanTwoTerms(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addSynonym('full-text:zed full-text:mid full-text:centre', 3.0, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $terms = $accumulator->getTerms();
        $this->assertArrayHasKey('centre, mid, zed', $terms);
    }

    /**
     * A malformed raw pair list (no colon at all) contributes nothing rather than crashing or storing a
     * garbage key.
     */
    public function testAddSynonymDoesNothingWhenNoPairHasAColon(): void
    {
        // Arrange
        $accumulator = new TermWeightAccumulator();

        // Act
        $accumulator->addSynonym('malformed-with-no-colon', 3.0, ExplanationParser::COMBINE_MODE_MAX);

        // Assert
        $this->assertSame([], $accumulator->getTerms());
    }
}
