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
        $this->assertSame([], $result[ExplanationParser::KEY_SCORE_FUNCTIONS]);
    }

    /**
     * `KEY_SCORE_FUNCTIONS` is always present in `parse()`'s return, even for an explain tree that
     * contains no `function_score` boost function at all — an empty array, not an absent key.
     *
     * @return void
     */
    public function testParseAlwaysReturnsAScoreFunctionsKeyEvenWhenNothingMatchesIt(): void
    {
        // Arrange
        $explanation = $this->createWeightNode('full-text', 'cable', 5.5);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertArrayHasKey(ExplanationParser::KEY_SCORE_FUNCTIONS, $result);
        $this->assertSame([], $result[ExplanationParser::KEY_SCORE_FUNCTIONS]);
    }

    /**
     * A leaf node whose description matches one of the documented `function_score` boost-function
     * phrasings (here a `gauss` decay function), has no `details`, and a non-zero value is collected into
     * `scoreFunctions` rather than falling through to the generic `otherContributions` bucket.
     *
     * @return void
     */
    public function testParseCollectsAFunctionScoreLeafIntoScoreFunctions(): void
    {
        // Arrange
        $description = 'gauss(created_at,2020-01-01,30d,,0.5)';
        $explanation = [
            'value' => 0.42,
            'description' => $description,
            'details' => [],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, []);

        // Assert
        $this->assertSame(
            [['description' => $description, 'value' => 0.42]],
            $result[ExplanationParser::KEY_SCORE_FUNCTIONS],
        );
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * A wrapping "function score, product of:" node has `details` of its own, so it must still be
     * recursed into like any other non-leaf node — NOT collected into `scoreFunctions` itself, even
     * though its own description also matches {@see ExplanationParser::SCORE_FUNCTION_PATTERN}. Only the
     * leaf function node underneath it ends up in `scoreFunctions`.
     *
     * @return void
     */
    public function testParseRecursesIntoAWrappingFunctionScoreNodeAndOnlyCollectsTheLeaf(): void
    {
        // Arrange
        $leafDescription = 'gauss(created_at,2020-01-01,30d,,0.5)';
        $explanation = [
            'value' => 5.0,
            'description' => 'function score, product of:',
            'details' => [
                ['value' => 5.0, 'description' => $leafDescription, 'details' => []],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, []);

        // Assert
        $this->assertSame(
            [['description' => $leafDescription, 'value' => 5.0]],
            $result[ExplanationParser::KEY_SCORE_FUNCTIONS],
        );
    }

    /**
     * A `most_fields`/`cross_fields`-without-blending multi_match combines its per-field scores under a
     * bool "should" ("sum of:"), so EVERY matching field genuinely adds to `_score` — unlike the dis_max
     * "max of:" case. `field` still names only the single largest individual contributor, as a "primary
     * contributor" hint, not a claim that it is the sole source of `total`.
     *
     * @return void
     */
    public function testParseSumsFieldWeightsForTheSameTermUnderASumCombiner(): void
    {
        // Arrange
        $explanation = [
            'value' => 25.82,
            'description' => 'sum of:',
            'details' => [
                $this->createWeightNode('fieldA', 'cable', 5.98),
                $this->createWeightNode('fieldB', 'cable', 19.84),
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $matchedToken = $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable'];
        $this->assertSame(25.82, $matchedToken['total']);
        $this->assertSame('fieldB', $matchedToken['field']);
    }

    /**
     * When no node anywhere in the ancestor chain indicates a combine mode (neither "max of:" nor
     * "sum of:"), the parser defaults to MAX as a conservative fallback for a genuinely unrecognized
     * explain shape — even though live verification against a real basic shop found its actual top-level
     * node uses "sum of:" (making this fallback branch rarely reached in practice there), MAX is kept as
     * the ultimate default since it's historically the more common multi-field text-search combiner shape
     * when the tree can't be classified at all. This is distinct from
     * {@see testParseTakesTheMaxOverFieldsAsATokenTotalRatherThanTheSum}, which exercises an EXPLICIT
     * "max of:" ancestor; this fixture's ancestor description matches neither combiner pattern at all.
     *
     * @return void
     */
    public function testParseDefaultsToMaxCombineModeWhenNoAncestorIndicatesAMode(): void
    {
        // Arrange
        $explanation = [
            'value' => 19.84,
            'description' => 'unrecognized combiner shape, product of:',
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
