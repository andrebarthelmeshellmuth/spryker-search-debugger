<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Explanation;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Explanation\Bm25BreakdownExtractor;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Explanation
 * @group Bm25BreakdownExtractorTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 * @group Portable
 */
class Bm25BreakdownExtractorTest extends Unit
{
    public function testExtractReturnsTheFullBreakdownForARealBm25Shape(): void
    {
        // Arrange
        $weightNode = $this->createWeightNode([
            'boost' => 2.2,
            'freq' => 8.0,
            'n' => 3.0,
            'capitalN' => 42.0,
            'k1' => 1.2,
            'b' => 0.75,
            'dl' => 5.0,
            'avgdl' => 4.2,
        ]);

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertSame(2.2, $breakdown['boost']);
        $this->assertSame(3.0, $breakdown['idf']['n']);
        $this->assertSame(42.0, $breakdown['idf']['capitalN']);
        $this->assertSame(8.0, $breakdown['tf']['freq']);
        $this->assertSame(1.2, $breakdown['tf']['k1']);
        $this->assertSame(0.75, $breakdown['tf']['b']);
        $this->assertSame(5.0, $breakdown['tf']['dl']);
        $this->assertSame(4.2, $breakdown['tf']['avgdl']);
    }

    public function testExtractReturnsNullWhenTheWeightNodeHasNoChildren(): void
    {
        // Arrange
        $weightNode = ['value' => 1.0, 'description' => 'weight(full-text:cable in 1)', 'details' => []];

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertNull($breakdown);
    }

    public function testExtractReturnsNullWhenTheScoreChildIsNotABm25Shape(): void
    {
        // Arrange — a different Similarity module entirely (e.g. ConstantScore), same overall structure.
        $weightNode = [
            'value' => 1.0,
            'description' => 'weight(full-text:cable in 1)',
            'details' => [
                ['value' => 1.0, 'description' => 'ConstantScore, computed as boost from:', 'details' => []],
            ],
        ];

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertNull($breakdown);
    }

    public function testExtractReturnsNullWhenTheBoostChildIsMissing(): void
    {
        // Arrange
        $weightNode = [
            'value' => 1.0,
            'description' => 'weight(full-text:cable in 1)',
            'details' => [
                [
                    'value' => 1.0,
                    'description' => 'score(freq=1.0), computed as boost * idf * tf from:',
                    'details' => [
                        $this->createIdfNode(1.0, 1.0),
                        $this->createTfNode(1.0, 1.0, 1.0, 1.0, 1.0),
                    ],
                ],
            ],
        ];

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertNull($breakdown);
    }

    public function testExtractReturnsNullWhenTheIdfChildIsMissingItsNChild(): void
    {
        // Arrange
        $weightNode = [
            'value' => 1.0,
            'description' => 'weight(full-text:cable in 1)',
            'details' => [
                [
                    'value' => 1.0,
                    'description' => 'score(freq=1.0), computed as boost * idf * tf from:',
                    'details' => [
                        ['value' => 2.2, 'description' => 'boost', 'details' => []],
                        [
                            'value' => 1.0,
                            'description' => 'idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:',
                            'details' => [
                                // Missing "n," — only the capital-N child is present.
                                ['value' => 42.0, 'description' => 'N, total number of documents with field', 'details' => []],
                            ],
                        ],
                        $this->createTfNode(1.0, 1.0, 1.0, 1.0, 1.0),
                    ],
                ],
            ],
        ];

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertNull($breakdown);
    }

    public function testExtractReturnsNullWhenTheTfChildIsMissingOneOfItsFiveChildren(): void
    {
        // Arrange
        $weightNode = [
            'value' => 1.0,
            'description' => 'weight(full-text:cable in 1)',
            'details' => [
                [
                    'value' => 1.0,
                    'description' => 'score(freq=1.0), computed as boost * idf * tf from:',
                    'details' => [
                        ['value' => 2.2, 'description' => 'boost', 'details' => []],
                        $this->createIdfNode(1.0, 1.0),
                        [
                            'value' => 1.0,
                            'description' => 'tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:',
                            'details' => [
                                ['value' => 1.0, 'description' => 'freq, occurrences of term within document', 'details' => []],
                                ['value' => 1.2, 'description' => 'k1, term saturation parameter', 'details' => []],
                                ['value' => 0.75, 'description' => 'b, length normalization parameter', 'details' => []],
                                ['value' => 5.0, 'description' => 'dl, length of field (approximate)', 'details' => []],
                                // "avgdl" deliberately omitted.
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $breakdown = (new Bm25BreakdownExtractor())->extract($weightNode);

        // Assert
        $this->assertNull($breakdown);
    }

    /**
     * @param array<string, float> $values
     *
     * @return array<string, mixed>
     */
    protected function createWeightNode(array $values): array
    {
        return [
            'value' => 1.0,
            'description' => 'weight(full-text:handcart in 7007) [PerFieldSimilarity], result of:',
            'details' => [
                [
                    'value' => 1.0,
                    'description' => sprintf('score(freq=%s), computed as boost * idf * tf from:', $values['freq']),
                    'details' => [
                        ['value' => $values['boost'], 'description' => 'boost', 'details' => []],
                        $this->createIdfNode($values['n'], $values['capitalN']),
                        $this->createTfNode($values['freq'], $values['k1'], $values['b'], $values['dl'], $values['avgdl']),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param float $n
     * @param float $capitalN
     *
     * @return array<string, mixed>
     */
    protected function createIdfNode(float $n, float $capitalN): array
    {
        return [
            'value' => 1.0,
            'description' => 'idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:',
            'details' => [
                ['value' => $n, 'description' => 'n, number of documents containing term', 'details' => []],
                ['value' => $capitalN, 'description' => 'N, total number of documents with field', 'details' => []],
            ],
        ];
    }

    /**
     * @param float $freq
     * @param float $k1
     * @param float $b
     * @param float $dl
     * @param float $avgdl
     *
     * @return array<string, mixed>
     */
    protected function createTfNode(float $freq, float $k1, float $b, float $dl, float $avgdl): array
    {
        return [
            'value' => 1.0,
            'description' => 'tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:',
            'details' => [
                ['value' => $freq, 'description' => 'freq, occurrences of term within document', 'details' => []],
                ['value' => $k1, 'description' => 'k1, term saturation parameter', 'details' => []],
                ['value' => $b, 'description' => 'b, length normalization parameter', 'details' => []],
                ['value' => $dl, 'description' => 'dl, length of field (approximate)', 'details' => []],
                ['value' => $avgdl, 'description' => 'avgdl, average length of field', 'details' => []],
            ],
        ];
    }
}
