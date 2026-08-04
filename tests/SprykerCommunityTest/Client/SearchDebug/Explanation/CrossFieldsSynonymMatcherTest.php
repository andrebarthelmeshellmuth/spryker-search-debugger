<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Explanation;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Explanation\CrossFieldsSynonymMatcher;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Explanation
 * @group CrossFieldsSynonymMatcherTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class CrossFieldsSynonymMatcherTest extends Unit
{
    /**
     * The real confirmed-live shape: two DIFFERENT terms ("switch"/"button"), each a directly
     * attributable weight leaf, combined under an ordinary "max of:" node.
     */
    public function testMatchCombinesTwoDistinctTermLeavesIntoOneKey(): void
    {
        // Arrange
        $details = [
            $this->createWeightLeaf('full-text', 'switch', 3.5),
            $this->createWeightLeaf('full-text', 'button', 5.428672),
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', $details, 5.428672);

        // Assert
        $this->assertSame('button, switch', $result['term']);
        $this->assertSame('full-text', $result['field']);
        $this->assertSame(5.428672, $result['winningNode']['value']);
    }

    /**
     * The winning leaf is whichever child's OWN value equals the group's reported value — never just the
     * first one — since that is the leaf whose field/breakdown actually explains the group's number.
     */
    public function testMatchIdentifiesTheWinningLeafByItsOwnValue(): void
    {
        // Arrange
        $details = [
            $this->createWeightLeaf('full-text', 'switch', 3.5),
            $this->createWeightLeaf('full-text-boosted', 'button', 9.1),
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', $details, 9.1);

        // Assert
        $this->assertSame('full-text-boosted', $result['field']);
        $this->assertSame(9.1, $result['winningNode']['value']);
    }

    /**
     * Every leaf sharing the SAME term (only the field differs) is an ordinary per-field dis_max, not a
     * synonym-alternatives position — must return null so the caller's normal recursion handles it
     * instead (each leaf calling addTerm() for the identical key already combines them correctly).
     */
    public function testMatchReturnsNullWhenEveryLeafSharesTheSameTerm(): void
    {
        // Arrange
        $details = [
            $this->createWeightLeaf('full-text', 'cable', 5.98),
            $this->createWeightLeaf('full-text-boosted', 'cable', 19.84),
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', $details, 19.84);

        // Assert
        $this->assertNull($result);
    }

    public function testMatchReturnsNullWithFewerThanTwoChildren(): void
    {
        // Arrange
        $details = [$this->createWeightLeaf('full-text', 'cable', 5.0)];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', $details, 5.0);

        // Assert
        $this->assertNull($result);
    }

    public function testMatchReturnsNullWhenTheDescriptionIsNotAMaxCombiner(): void
    {
        // Arrange
        $details = [
            $this->createWeightLeaf('full-text', 'switch', 3.5),
            $this->createWeightLeaf('full-text', 'button', 5.0),
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('sum of:', $details, 5.0);

        // Assert
        $this->assertNull($result);
    }

    /**
     * A single non-term-weight, non-"max of:" child means this isn't a pure disjunction-of-term-weights
     * tree at all — bail rather than guess at a partial flattening.
     */
    public function testMatchReturnsNullWhenAChildIsNeitherATermWeightNorANestedMaxNode(): void
    {
        // Arrange
        $details = [
            $this->createWeightLeaf('full-text', 'switch', 3.5),
            ['value' => 5.0, 'description' => 'some unrecognized node', 'details' => []],
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', $details, 5.0);

        // Assert
        $this->assertNull($result);
    }

    /**
     * A multi-field query with a synonym at one position produces an OUTER "max of:" choosing between an
     * INNER per-field "max of:" for each synonym alternative — max-of-maxes must flatten down to the real
     * leaves regardless of nesting depth.
     */
    public function testMatchFlattensNestedMaxOfMaxNodes(): void
    {
        // Arrange
        $innerSwitch = [
            'value' => 3.5,
            'description' => 'max of:',
            'details' => [
                $this->createWeightLeaf('full-text', 'switch', 2.0),
                $this->createWeightLeaf('full-text-boosted', 'switch', 3.5),
            ],
        ];
        $innerButton = [
            'value' => 5.428672,
            'description' => 'max of:',
            'details' => [
                $this->createWeightLeaf('full-text', 'button', 4.1),
                $this->createWeightLeaf('full-text-boosted', 'button', 5.428672),
            ],
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', [$innerSwitch, $innerButton], 5.428672);

        // Assert
        $this->assertSame('button, switch', $result['term']);
        $this->assertSame('full-text-boosted', $result['field']);
    }

    /**
     * The mirror image of {@see testMatchFlattensNestedMaxOfMaxNodes()}: when the INNER "max of:" node
     * itself fails to resolve (one of ITS children is neither a term-weight leaf nor a valid nested max
     * node), that failure must propagate all the way back out through the OUTER call as null too, rather
     * than the outer level silently treating the unresolved inner group as if it had produced zero leaves.
     */
    public function testMatchReturnsNullWhenANestedMaxOfNodeFailsToResolve(): void
    {
        // Arrange
        $unresolvableInner = [
            'value' => 3.5,
            'description' => 'max of:',
            'details' => [
                $this->createWeightLeaf('full-text', 'switch', 2.0),
                ['value' => 3.5, 'description' => 'some unrecognized node', 'details' => []],
            ],
        ];
        $innerButton = [
            'value' => 5.428672,
            'description' => 'max of:',
            'details' => [
                $this->createWeightLeaf('full-text', 'button', 4.1),
                $this->createWeightLeaf('full-text-boosted', 'button', 5.428672),
            ],
        ];

        // Act
        $result = (new CrossFieldsSynonymMatcher())->match('max of:', [$unresolvableInner, $innerButton], 5.428672);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @param string $field
     * @param string $term
     * @param float $value
     *
     * @return array<string, mixed>
     */
    protected function createWeightLeaf(string $field, string $term, float $value): array
    {
        return [
            'value' => $value,
            'description' => sprintf('weight(%s:%s in 42) [PerFieldSimilarity], result of:', $field, $term),
            'details' => [],
        ];
    }
}
