<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Explanation;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group Catalog
 * @group SearchDebug
 * @group ExplanationParserTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class ExplanationParserTest extends Unit
{
    /**
     * @return void
     */
    public function testParseAttributesAQueryTokenWeightToTheMatchedTokens(): void
    {
        // Arrange
        $explanation = $this->createWeightNode('full-text', 'cable', 5.5);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame(
            ['cable' => ['total' => 5.5, 'field' => 'full-text']],
            $result[ExplanationParser::KEY_MATCHED_TOKENS],
        );
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * A numeric term is stored as an int PHP array key. It must still be recognized as one of the
     * (string) query tokens, otherwise every numeric search term is demoted to an "other contribution".
     *
     * @return void
     */
    public function testParseAttributesANumericQueryTokenWeightToTheMatchedTokens(): void
    {
        // Arrange
        $explanation = $this->createWeightNode('full-text', '3000', 8.25);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['3000']);

        // Assert
        $this->assertArrayHasKey('3000', $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * @return void
     */
    public function testParseMovesATermThatIsNotAQueryTokenToTheOtherContributions(): void
    {
        // Arrange
        $explanation = $this->createWeightNode('type', 'product_abstract', 0.1656);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame([], $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame(
            [['description' => 'type:product_abstract', 'value' => 0.1656]],
            $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS],
        );
    }

    /**
     * A `best_fields` multi_match combines its per-field scores under a dis_max ("max of"), so only the
     * best-scoring field contributes to `_score`. Summing them would not add up to the document's score.
     *
     * @return void
     */
    public function testParseTakesTheMaxOverFieldsAsATokenTotalRatherThanTheSum(): void
    {
        // Arrange
        $explanation = [
            'value' => 19.84,
            'description' => 'max of:',
            'details' => [
                $this->createWeightNode('full-text', 'cable', 5.98),
                $this->createWeightNode('full-text-boosted', 'cable', 19.84),
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $matchedToken = $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable'];
        $this->assertSame(19.84, $matchedToken['total']);
        $this->assertSame('full-text-boosted', $matchedToken['field']);
        // Only the contributing (winning) field is part of the output — the losing field's own weight
        // adds nothing to the score under dis_max and is deliberately not carried along.
        $this->assertSame(['total', 'field'], array_keys($matchedToken));
    }

    /**
     * An unattributable weight node (e.g. a Synonym over several terms) must be kept verbatim: descending
     * into it would surface its multiplicative TF/IDF internals as if they were additive score parts.
     *
     * @return void
     */
    public function testParseKeepsAnUnrecognizedWeightNodeVerbatimInsteadOfDescendingIntoIt(): void
    {
        // Arrange
        $description = 'weight(Synonym(full-text:cable full-text:cabel) in 42) [PerFieldSimilarity], result of:';
        $explanation = [
            'value' => 3.5,
            'description' => $description,
            'details' => [
                ['value' => 2.2, 'description' => 'boost', 'details' => []],
                ['value' => 4.31, 'description' => 'idf', 'details' => []],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame([], $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame(
            [['description' => $description, 'value' => 3.5]],
            $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS],
        );
    }

    /**
     * Lucene explains filter-context clauses for transparency but excludes them from scoring: they report
     * 0 at every ancestor level despite non-zero literals deeper inside.
     *
     * @return void
     */
    public function testParseIgnoresAZeroValuedNodeAndItsChildren(): void
    {
        // Arrange
        $explanation = [
            'value' => 0.0,
            'description' => 'match on required clause, product of:',
            'details' => [
                ['value' => 1.0, 'description' => '*:*', 'details' => []],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame([], $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * @param string $field
     * @param string $term
     * @param float $value
     *
     * @return array<string, mixed>
     */
    protected function createWeightNode(string $field, string $term, float $value): array
    {
        return [
            'value' => $value,
            'description' => sprintf('weight(%s:%s in 42) [PerFieldSimilarity], result of:', $field, $term),
            'details' => [
                ['value' => $value, 'description' => 'score(freq=1.0), computed as boost * idf * tf from:', 'details' => []],
            ],
        ];
    }
}
