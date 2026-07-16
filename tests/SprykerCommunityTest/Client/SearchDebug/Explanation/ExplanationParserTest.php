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
     * A genuinely unattributable weight node (neither a plain term nor a recognized Synonym group) must
     * be kept verbatim: descending into it would surface its multiplicative TF/IDF internals as if they
     * were additive score parts.
     *
     * @return void
     */
    public function testParseKeepsAnUnrecognizedWeightNodeVerbatimInsteadOfDescendingIntoIt(): void
    {
        // Arrange
        $description = 'weight(ConstantScore(full-text:cable) in 42) [PerFieldSimilarity], result of:';
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
     * Regression test: confirmed live against a real product ("Cherry Optical Mouse ...") matching this
     * shop's "switch, button" synonym rule and the query "brenne switch". Elasticsearch scores a synonym
     * group as ONE INSEPARABLE node — "weight(Synonym(full-text:button full-text:switch) in N)" — not one
     * node per term, so it cannot be attributed to "switch" or "button" independently without either
     * double-counting the value (attributing it to both) or dropping it from the visible per-token sum
     * entirely (the bug this test guards against: before this fix, the node fell through to
     * `otherContributions` UNLABELED, and the query's other matched-token totals visibly fell short of
     * the document's real `_score`).
     *
     * @return void
     */
    public function testParseAttributesASynonymGroupWeightToACombinedTermKey(): void
    {
        // Arrange — the exact shape confirmed live, trimmed to the parts this parser reads.
        $description = 'weight(Synonym(full-text:button full-text:switch) in 12803) [PerFieldSimilarity], result of:';
        $explanation = [
            'value' => 5.428672,
            'description' => $description,
            'details' => [
                ['value' => 2.2, 'description' => 'boost', 'details' => []],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['brenne', 'switch', 'button']);

        // Assert — sorted, joined key; nothing left behind in otherContributions.
        $this->assertSame(
            ['button, switch' => ['total' => 5.428672, 'field' => 'full-text']],
            $result[ExplanationParser::KEY_MATCHED_TOKENS],
        );
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * A synonym rule can list any number of equivalent words — this parser must not assume exactly 2.
     *
     * @return void
     */
    public function testParseAttributesASynonymGroupWithMoreThanTwoTermsToOneCombinedKey(): void
    {
        // Arrange
        $description = 'weight(Synonym(full-text:accu full-text:battery full-text:cell full-text:power) in 7) [PerFieldSimilarity], result of:';
        $explanation = ['value' => 4.0, 'description' => $description, 'details' => []];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['power']);

        // Assert
        $this->assertSame(
            ['accu, battery, cell, power' => ['total' => 4.0, 'field' => 'full-text']],
            $result[ExplanationParser::KEY_MATCHED_TOKENS],
        );
    }

    /**
     * A synonym group whose terms are NOT among the user's actual query tokens (e.g. it matched via a
     * different query position, or the query itself changed) belongs under "other contributions", same
     * as any other non-query term — not silently dropped or force-matched.
     *
     * @return void
     */
    public function testParseMovesASynonymGroupThatIsNotAQueryTokenToTheOtherContributions(): void
    {
        // Arrange
        $description = 'weight(Synonym(full-text:accu full-text:battery) in 7) [PerFieldSimilarity], result of:';
        $explanation = ['value' => 4.0, 'description' => $description, 'details' => []];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame([], $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame(
            [['description' => 'full-text:accu, battery', 'value' => 4.0]],
            $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS],
        );
    }

    /**
     * The SAME synonym group scored via two different fields (this shop's real "full-text" AND
     * "full-text-boosted") must combine through the identical MAX/SUM logic a single real term already
     * gets — reused, not reimplemented, for the combined key.
     *
     * @return void
     */
    public function testParseCombinesASynonymGroupsPerFieldWeightsTheSameWayAsARealTerm(): void
    {
        // Arrange
        $explanation = [
            'value' => 45.650497,
            'description' => 'max of:',
            'details' => [
                [
                    'value' => 5.428672,
                    'description' => 'weight(Synonym(full-text:button full-text:switch) in 12803) [PerFieldSimilarity], result of:',
                    'details' => [],
                ],
                [
                    'value' => 45.650497,
                    'description' => 'weight(Synonym(full-text-boosted:button full-text-boosted:switch) in 12803) [PerFieldSimilarity], result of:',
                    'details' => [],
                ],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['switch', 'button']);

        // Assert
        $matchedToken = $result[ExplanationParser::KEY_MATCHED_TOKENS]['button, switch'];
        $this->assertSame(45.650497, $matchedToken['total']);
        $this->assertSame('full-text-boosted', $matchedToken['field']);
    }

    /**
     * Regression test: confirmed live against the shop's REAL, actual production query — a `cross_fields`
     * multi_match, not the `best_fields` shape the tests above exercise. This is a STRUCTURALLY DIFFERENT
     * way a synonym group shows up: no `Synonym(...)` node at all — Lucene scores "switch" and "button" as
     * two entirely separate, independently-attributable `weight(field:term)` leaves, combined by an
     * ordinary "max of:" node exactly like a real term's per-FIELD weights would be. Before this fix, both
     * leaves got attributed IN FULL as separate matched tokens (5.417414 AND 5.236648), when only the
     * winning one should count (Lucene itself already computed the "max of:" node's value as 5.417414) —
     * inflating the displayed per-token sum past the document's real `_score`. This is the live search
     * page bug report this guards: query "brenne switch" on a real "Brennenstuhl socket strip" product,
     * displayed as "12, 5, 5" not adding up to the real total of ~18.27.
     *
     * @return void
     */
    public function testParseCollapsesACrossFieldsSynonymAlternativesNodeToOneCombinedKey(): void
    {
        // Arrange — the exact shape confirmed live (trimmed to the "switch"/"button" position; the real
        // tree also has a sibling "max of:" for "brenne", handled as an ordinary per-field term already
        // covered by testParseTakesTheMaxOverFieldsAsATokenTotalRatherThanTheSum).
        $explanation = [
            'value' => 5.417414,
            'description' => 'max of:',
            'details' => [
                $this->createWeightNode('full-text', 'switch', 5.417414),
                $this->createWeightNode('full-text', 'button', 5.236648),
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['brenne', 'switch', 'button']);

        // Assert — the node's OWN value (the max Lucene already computed), not the sum of both leaves.
        $this->assertSame(
            ['button, switch' => ['total' => 5.417414, 'field' => 'full-text']],
            $result[ExplanationParser::KEY_MATCHED_TOKENS],
        );
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * The SAME "max of:" shape, but for a REAL term matched on two different fields (no synonym involved)
     * — must NOT be treated as a synonym-alternatives position, since all children share the identical
     * term text. This is what distinguishes the two cases: term-text diversity among the children, not
     * the node shape, which is otherwise identical to the synonym case above.
     *
     * @return void
     */
    public function testParseDoesNotCollapseAMaxOfNodeWhoseChildrenShareTheSameTerm(): void
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

        // Assert — the ordinary per-field term path, not a combined key.
        $this->assertArrayHasKey('cable', $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertArrayNotHasKey('cable, cable', $result[ExplanationParser::KEY_MATCHED_TOKENS]);
        $this->assertSame(19.84, $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable']['total']);
    }

    /**
     * A "max of:" node with only ONE child is never a real dis_max between alternatives (there's nothing
     * to choose between) — must fall through to normal recursion rather than being (mis)treated as a
     * single-term "synonym group of one".
     *
     * @return void
     */
    public function testParseDoesNotCollapseAMaxOfNodeWithOnlyOneChild(): void
    {
        // Arrange
        $explanation = [
            'value' => 5.5,
            'description' => 'max of:',
            'details' => [
                $this->createWeightNode('full-text', 'cable', 5.5),
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame(
            ['cable' => ['total' => 5.5, 'field' => 'full-text']],
            $result[ExplanationParser::KEY_MATCHED_TOKENS],
        );
    }

    /**
     * A "max of:" node whose children are NOT directly-attributable term-weight leaves (e.g. a nested
     * wrapper, or an unrecognized shape) must fall through to normal recursion rather than being
     * incorrectly collapsed — this parser only special-cases the shape it can actually verify.
     *
     * @return void
     */
    public function testParseDoesNotCollapseAMaxOfNodeWithANonLeafChild(): void
    {
        // Arrange
        $explanation = [
            'value' => 19.84,
            'description' => 'max of:',
            'details' => [
                $this->createWeightNode('full-text', 'cable', 5.98),
                [
                    'value' => 19.84,
                    'description' => 'sum of:',
                    'details' => [$this->createWeightNode('full-text-boosted', 'cable', 19.84)],
                ],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert — still resolves correctly via normal recursion, just not via the fast-path collapse.
        $this->assertSame(19.84, $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable']['total']);
    }

    /**
     * Regression test: confirmed live against the SAME real product/query as the tests above, but this
     * time reproducing the EXACT nesting Elasticsearch actually returned — unlike the simplified 2-level
     * fixture above, a synonym-expanded term wraps EACH field's weight in its own extra, SINGLE-CHILD
     * "sum of:" node (plain non-synonym term matches get no such wrapper — confirmed by comparing against
     * a real explain for a plain-term query on the same document). Before this fix, that inner "sum of:"
     * was trusted as a combine-mode signal and overrode the ancestor "max of:" that actually governs how
     * the two FIELDS combine (dis_max — only the winning field counts), causing the two per-field
     * weights to be SUMMED instead of MAX'd: 5.428672 + 45.650497 = 51.079169, inflating the matched
     * token's total past the document's real `_score` of 45.650497 — the exact bug report this guards.
     *
     * @return void
     */
    public function testParseTreatsASingleChildWrapperNodeAsCombineModeNeutral(): void
    {
        // Arrange — mirrors the real explain tree's shape: sum of: > max of: > sum of: (1 child each) > weight(...)
        $explanation = [
            'value' => 45.650497,
            'description' => 'sum of:',
            'details' => [
                [
                    'value' => 45.650497,
                    'description' => 'max of:',
                    'details' => [
                        [
                            'value' => 5.428672,
                            'description' => 'sum of:',
                            'details' => [
                                [
                                    'value' => 5.428672,
                                    'description' => 'weight(Synonym(full-text:button full-text:switch) in 12803) [PerFieldSimilarity], result of:',
                                    'details' => [],
                                ],
                            ],
                        ],
                        [
                            'value' => 45.650497,
                            'description' => 'sum of:',
                            'details' => [
                                [
                                    'value' => 45.650497,
                                    'description' => 'weight(Synonym(full-text-boosted:button full-text-boosted:switch) in 12803) [PerFieldSimilarity], result of:',
                                    'details' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                // The zero-valued internal filter clause every catalog query includes — present in the
                // real tree, kept here so this fixture is a faithful, complete reproduction.
                [
                'value' => 0.0,
                'description' => 'match on required clause, product of:',
                'details' => [
                    ['value' => 1.0, 'description' => '*:*', 'details' => []],
                ]],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['brenne', 'switch', 'button']);

        // Assert — MAX of the two fields (45.650497), not their sum (51.079169).
        $matchedToken = $result[ExplanationParser::KEY_MATCHED_TOKENS]['button, switch'];
        $this->assertSame(45.650497, $matchedToken['total']);
        $this->assertSame('full-text-boosted', $matchedToken['field']);
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * The same single-child-wrapper neutrality applies to a plain (non-synonym) term too, even though a
     * real plain-term match doesn't happen to produce this shape today (confirmed live) — the fix is
     * general, not synonym-specific, and must not regress the ordinary per-field dis_max case either.
     *
     * @return void
     */
    public function testParseTreatsASingleChildWrapperNodeAsCombineModeNeutralForAPlainTermToo(): void
    {
        // Arrange
        $explanation = [
            'value' => 19.84,
            'description' => 'max of:',
            'details' => [
                [
                    'value' => 5.98,
                    'description' => 'sum of:',
                    'details' => [$this->createWeightNode('full-text', 'cable', 5.98)],
                ],
                [
                    'value' => 19.84,
                    'description' => 'sum of:',
                    'details' => [$this->createWeightNode('full-text-boosted', 'cable', 19.84)],
                ],
            ],
        ];

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert — still MAX (19.84), not SUM (25.82), despite the single-child "sum of:" wrappers.
        $matchedToken = $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable'];
        $this->assertSame(19.84, $matchedToken['total']);
        $this->assertSame('full-text-boosted', $matchedToken['field']);
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

    /**
     * The EXACT tree a real script_score function_score produces (captured live from the
     * search-ranking business-signal query, ES 7, boost_mode "replace"): the script node's value is the
     * final score, its `_score:` child carries the wrapped query's own relevance, the term breakdown
     * lives underneath that, and a float-max `maxBoost` sentinel sits next to the script node.
     *
     * @return void
     */
    public function testParseExtractsQueryScoreAndTermBreakdownFromARealScriptScoreTree(): void
    {
        // Arrange
        $explanation = $this->createScriptScoreExplanationTree(2.7416, 6.9244);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert: the wrapped query's own relevance is exposed separately ...
        $this->assertSame(6.9244, $result[ExplanationParser::KEY_QUERY_SCORE]);

        // ... the familiar term breakdown still works through the wrapper ...
        $this->assertSame(6.9244, $result[ExplanationParser::KEY_MATCHED_TOKENS]['cable']['total']);

        // ... the script function itself is recorded with the FINAL value ...
        $this->assertCount(1, $result[ExplanationParser::KEY_SCORE_FUNCTIONS]);
        $this->assertSame(2.7416, $result[ExplanationParser::KEY_SCORE_FUNCTIONS][0]['value']);
    }

    /**
     * The float-max `maxBoost` sentinel (3.4028235E38) every function_score explain contains must never
     * surface as a score contribution — it is a cap marker, not a score part.
     *
     * @return void
     */
    public function testParseSuppressesTheMaxBoostSentinel(): void
    {
        // Arrange
        $explanation = $this->createScriptScoreExplanationTree(2.7416, 6.9244);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertSame([], $result[ExplanationParser::KEY_OTHER_CONTRIBUTIONS]);
    }

    /**
     * Without a function_score wrapper there is no separate query score — the key must still exist
     * (null), so consumers can distinguish "no wrapper" from "wrapper with score 0".
     *
     * @return void
     */
    public function testParseReturnsNullQueryScoreWithoutAScriptScoreWrapper(): void
    {
        // Arrange
        $explanation = $this->createWeightNode('full-text', 'cable', 6.9244);

        // Act
        $result = (new ExplanationParser())->parse($explanation, ['cable']);

        // Assert
        $this->assertNull($result[ExplanationParser::KEY_QUERY_SCORE]);
    }

    /**
     * Live-captured shape (abbreviated to the structurally relevant nodes) of
     * `function_score` + `script_score` + `boost_mode: replace` explain output.
     *
     * @param float $finalScore
     * @param float $queryScore
     *
     * @return array<string, mixed>
     */
    protected function createScriptScoreExplanationTree(float $finalScore, float $queryScore): array
    {
        return [
            'value' => $finalScore,
            'description' => 'sum of:',
            'details' => [
                [
                    'value' => $finalScore,
                    'description' => 'min of:',
                    'details' => [
                        [
                            'value' => $finalScore,
                            'description' => 'script score function, computed with script:"Script{type=inline, lang=\'painless\', idOrCode=\'(1 + Math.sqrt(_score)) * (params.w0 * ...)\'}"',
                            'details' => [
                                [
                                    'value' => $queryScore,
                                    'description' => '_score: ',
                                    'details' => [
                                        $this->createWeightNode('full-text', 'cable', $queryScore),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'value' => 3.4028235E38,
                            'description' => 'maxBoost',
                            'details' => [],
                        ],
                    ],
                ],
                [
                    'value' => 0.0,
                    'description' => 'match on required clause, product of:',
                    'details' => [],
                ],
            ],
        ];
    }

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
