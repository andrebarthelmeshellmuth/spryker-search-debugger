<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Analyzer;

use Codeception\Test\Unit;
use SprykerCommunityTest\Client\SearchDebug\Fixture\TestPageIndexTrait;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch, but against a TEST-OWNED index (`TestPageIndexTrait`),
 * not the host shop's real `page.json`/live index. This is deliberate: the two things that can silently
 * be wrong — the resolved index name and the analyzer name — are exactly the things a mocked Elastica
 * client would happily accept while returning nothing useful, so a real round-trip stays essential. But
 * asserting against a SHARED shop index means every content change to that shop's `page.json` breaks
 * these exact-value assertions (as happened when this shop's own config grew richer) — a test-owned index
 * with its own deliberately stable analysis config decouples the two, and makes these tests portable to
 * any shop installing this package, not just this one.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchElasticsearch
 * @group Analyzer
 * @group SearchStringAnalyzerTest
 * Add your own group annotations below this line
 * @group NeedsSearch
 */
class SearchStringAnalyzerTest extends Unit
{
    use TestPageIndexTrait;

    protected function _before(): void
    {
        $this->createTestPageIndex();
    }

    protected function _after(): void
    {
        $this->deleteTestPageIndex();
    }

    /**
     * The query-time analyzer runs `lowercase`, the synonym/word-delimiter/stop-words/min-length filters,
     * but no edge-ngram (that one is index-time only) — a hyphenated compound therefore splits into whole
     * lowercased words, none of which are synonym sources, stopwords, or delimiter-worthy.
     */
    public function testGetSearchStringTokensReturnsTheQueryTimeAnalyzerTokens(): void
    {
        // Act
        $tokens = $this->createTestSearchStringAnalyzer()->getTokens('Eisen-Hammer');

        // Assert
        $this->assertSame(['eisen', 'hammer'], $tokens);
    }

    public function testGetSearchStringTokensLowercasesASingleTerm(): void
    {
        // Act
        $tokens = $this->createTestSearchStringAnalyzer()->getTokens('CABLE');

        // Assert
        $this->assertSame(['cable'], $tokens);
    }

    /**
     * An empty search string must not cause a request to Elasticsearch at all.
     */
    public function testGetSearchStringTokensReturnsAnEmptyListForAnEmptySearchString(): void
    {
        // Act
        $tokens = $this->createTestSearchStringAnalyzer()->getTokens('');

        // Assert
        $this->assertSame([], $tokens);
    }

    /**
     * `fulltext_index_analyzer` is a custom analyzer (tokenizer + filter chain, not a built-in named
     * one) — Elasticsearch's `explain` response therefore nests tokens under `tokenfilters[]`, not under
     * a top-level `analyzer` key. Only a real round-trip catches a wrong assumption about that shape.
     *
     * This is deliberately the INDEX-time analyzer, unlike `getTokens()` above — it includes the
     * edge-ngram filter, so a word explodes into every 2-to-20-char prefix, each one reported at the
     * OFFSET OF THE WHOLE WORD it came from (not the prefix's own span) — e.g. "ei" here is
     * `startOffset: 0, endOffset: 5`, the same span as "eisen", not `endOffset: 2`. That's intentional and
     * relied upon downstream (`TokenHighlighter`): highlighting a short query token like "öl" that only
     * matched via a prefix still highlights the whole word ("Ölpapier") it was found in, not an
     * out-of-context 2-character fragment.
     */
    public function testGetTextTokenOffsetsReturnsTokensWithOffsetsIntoTheOriginalText(): void
    {
        // Act
        $tokenOffsets = $this->createTestSearchStringAnalyzer()->getTokenOffsets('Eisen-Hammer');

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
     * `$useSearchAnalyzer = true` must resolve `fulltext_search_analyzer` instead — the fixture's search
     * analyzer shares every filter with the index one EXCEPT the edge-ngram filter, so the same input
     * that explodes into 9 prefix tokens above must come back as exactly the 2 whole words here, each at
     * its own real offset (not the whole-word offset every ngram prefix above shares).
     */
    public function testGetTokenOffsetsUsesTheSearchTimeAnalyzerWhenRequested(): void
    {
        // Act
        $tokenOffsets = $this->createTestSearchStringAnalyzer()->getTokenOffsets('Eisen-Hammer', true);

        // Assert
        $this->assertSame(
            [
                ['token' => 'eisen', 'startOffset' => 0, 'endOffset' => 5],
                ['token' => 'hammer', 'startOffset' => 6, 'endOffset' => 12],
            ],
            $tokenOffsets,
        );
    }

    public function testGetTextTokenOffsetsReturnsAnEmptyListForEmptyText(): void
    {
        // Act
        $tokenOffsets = $this->createTestSearchStringAnalyzer()->getTokenOffsets('');

        // Assert
        $this->assertSame([], $tokenOffsets);
    }

    /**
     * The batched call must return byte-for-byte the SAME offsets `getTokenOffsets()` returns per text,
     * called individually — a real round-trip is essential here specifically: this asserts the rebasing
     * math against Elasticsearch's REAL cumulative-offset behavior for an array `text`, not an assumption
     * about it. Includes "Ölpapier" deliberately — a multi-byte UTF-8 character early in one of the
     * batched texts, so a rebasing bug that only manifests once a text contains a non-ASCII character
     * would still be caught here.
     */
    public function testGetTokenOffsetsForTextsReturnsTheSameOffsetsAsIndividualCallsForEachText(): void
    {
        // Arrange
        $analyzer = $this->createTestSearchStringAnalyzer();

        // Act
        $batched = $analyzer->getTokenOffsetsForTexts(['Eisen-Hammer', 'CABLE', 'Ölpapier']);

        // Assert
        $this->assertSame($analyzer->getTokenOffsets('Eisen-Hammer'), $batched['Eisen-Hammer']);
        $this->assertSame($analyzer->getTokenOffsets('CABLE'), $batched['CABLE']);
        $this->assertSame($analyzer->getTokenOffsets('Ölpapier'), $batched['Ölpapier']);
    }

    /**
     * A text appearing more than once is analyzed once, not once per occurrence — the SAME text-keyed
     * result entry answers for every occurrence a caller had. An empty string is dropped entirely, same
     * as `getTokenOffsets('')` short-circuiting rather than issuing a request.
     */
    public function testGetTokenOffsetsForTextsDeduplicatesRepeatedTextsAndDropsEmptyOnes(): void
    {
        // Act
        $batched = $this->createTestSearchStringAnalyzer()->getTokenOffsetsForTexts(['CABLE', 'CABLE', '', 'CABLE']);

        // Assert
        $this->assertSame(['CABLE'], array_keys($batched));
    }

    public function testGetTokenOffsetsForTextsReturnsAnEmptyArrayForAnEmptyList(): void
    {
        // Act
        $batched = $this->createTestSearchStringAnalyzer()->getTokenOffsetsForTexts([]);

        // Assert
        $this->assertSame([], $batched);
    }

    /**
     * A single-text call takes the same short-circuit `getTokenOffsets()` itself uses, rather than a
     * batched request of one — no behavioral difference, just confirming the shortcut still returns the
     * expected text-keyed shape.
     */
    public function testGetTokenOffsetsForTextsWithASingleTextMatchesGetTokenOffsets(): void
    {
        // Arrange
        $analyzer = $this->createTestSearchStringAnalyzer();

        // Act
        $batched = $analyzer->getTokenOffsetsForTexts(['Eisen-Hammer']);

        // Assert
        $this->assertSame(['Eisen-Hammer' => $analyzer->getTokenOffsets('Eisen-Hammer')], $batched);
    }

    /**
     * Every stage of the real index-time pipeline, in chain order: the char filter first (whole-text,
     * before tokenization), then the tokenizer, then each token filter. This is the same `_analyze`
     * shape `getTokenOffsets()` above reads, just without collapsing everything down to the final stage.
     *
     * `TestPageIndexTrait`'s config is deliberately rich, to exercise every `definition`/
     * `definitionTruncated` shape against real Elasticsearch rather than only invented unit-test
     * fixtures: a char filter (`unit_symbol_normalizer`, a no-op for this input — no µ/& in "Ölpapier"),
     * the standard tokenizer, `lowercase`, `fulltext_synonyms` (a no-op for this input too, but its OWN
     * definition is still reported — the >5-item synonym list makes `definitionTruncated` true regardless
     * of whether THIS text happened to match one), `fulltext_word_delimiter` (a `word_delimiter_graph`
     * with several BOOLEAN config values). Note: Elasticsearch's live `_settings` response reports these
     * booleans as the STRINGS `"true"`/`"false"`, not native JSON booleans (confirmed live, same
     * normalization behavior as `min_gram`/`max_gram` coming back as strings) — so
     * `ComponentDefinitionFormatter`'s explicit `is_bool()` handling is defensive-correctness for the
     * shape ES's settings API COULD return, not something this specific live round-trip exercises; either
     * code path produces the same `"true"`/`"false"` text here.
     */
    public function testGetTextAnalysisStagesReturnsEveryPipelineStageInChainOrder(): void
    {
        // Act
        $stages = $this->createTestSearchStringAnalyzer()->getAnalysisStages('Ölpapier');

        // Assert
        $this->assertCount(8, $stages);

        $this->assertSame('char filter: unit_symbol_normalizer', $stages[0]['operation']);
        $this->assertSame([['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[0]['tokens']);
        $this->assertSame('mapping (mappings: µ => u, & => and)', $stages[0]['definition']);
        $this->assertFalse($stages[0]['definitionTruncated']);

        $this->assertSame('tokenizer: standard', $stages[1]['operation']);
        $this->assertSame([['token' => 'Ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[1]['tokens']);
        // "standard" is a built-in tokenizer, used by name only, with nothing custom configured for it.
        $this->assertNull($stages[1]['definition']);

        $this->assertSame('filter: lowercase', $stages[2]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[2]['tokens']);
        // "lowercase" is likewise a built-in filter — nothing custom to show for it either.
        $this->assertNull($stages[2]['definition']);

        $this->assertSame('filter: fulltext_synonyms', $stages[3]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[3]['tokens']);
        $this->assertSame('synonym (synonyms: (6 total))', $stages[3]['definition']);
        $this->assertTrue($stages[3]['definitionTruncated']);

        $this->assertSame('filter: fulltext_word_delimiter', $stages[4]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[4]['tokens']);
        // Not a single `assertSame` on the whole string: Elasticsearch does not guarantee returning this
        // JSON-object-shaped config's keys in declaration order (confirmed live — see
        // `IndexSchemaReaderTest::testFindComponentReturnsBooleanConfigValuesAsStrings()`), so only the
        // prefix and the presence of each key: value pair are meaningful to assert on, not their order.
        $this->assertStringStartsWith('word_delimiter_graph (', $stages[4]['definition']);
        $this->assertStringContainsString('generate_word_parts: true', $stages[4]['definition']);
        $this->assertStringContainsString('catenate_words: true', $stages[4]['definition']);
        $this->assertStringContainsString('preserve_original: true', $stages[4]['definition']);
        $this->assertFalse($stages[4]['definitionTruncated']);

        $this->assertSame('filter: fulltext_stop_words', $stages[5]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[5]['tokens']);
        $this->assertSame('stop (stopwords: und, oder, der, die, das)', $stages[5]['definition']);
        $this->assertFalse($stages[5]['definitionTruncated']);

        $this->assertSame('filter: fulltext_min_length', $stages[6]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[6]['tokens']);
        $this->assertSame('length (min: 2)', $stages[6]['definition']);

        $this->assertSame('filter: fulltext_index_ngram_filter', $stages[7]['operation']);
        $this->assertContains(
            ['token' => 'öl', 'startOffset' => 0, 'endOffset' => 8],
            $stages[7]['tokens'],
        );
        $this->assertSame('edge_ngram (min_gram: 2, max_gram: 20)', $stages[7]['definition']);
    }

    /**
     * The fixture's search analyzer shares every stage with the index one EXCEPT the trailing edge-ngram
     * filter — same char filter, tokenizer, and first 5 filters, verified here by re-asserting the exact
     * same operations/tokens the index-analyzer test above does, then confirming the ngram stage the
     * index analyzer adds on top is genuinely absent, not just unasserted.
     */
    public function testGetTextAnalysisStagesUsesTheSearchTimeAnalyzerWhenRequested(): void
    {
        // Act
        $stages = $this->createTestSearchStringAnalyzer()->getAnalysisStages('Ölpapier', true);

        // Assert
        $this->assertCount(7, $stages);

        $this->assertSame('char filter: unit_symbol_normalizer', $stages[0]['operation']);
        $this->assertSame('tokenizer: standard', $stages[1]['operation']);
        $this->assertSame('filter: lowercase', $stages[2]['operation']);
        $this->assertSame('filter: fulltext_synonyms', $stages[3]['operation']);
        $this->assertSame('filter: fulltext_word_delimiter', $stages[4]['operation']);
        $this->assertSame('filter: fulltext_stop_words', $stages[5]['operation']);

        $this->assertSame('filter: fulltext_min_length', $stages[6]['operation']);
        $this->assertSame([['token' => 'ölpapier', 'startOffset' => 0, 'endOffset' => 8]], $stages[6]['tokens']);

        foreach ($stages as $stage) {
            $this->assertNotSame('filter: fulltext_index_ngram_filter', $stage['operation']);
        }
    }

    public function testGetTextAnalysisStagesReturnsAnEmptyListForEmptyText(): void
    {
        // Act
        $stages = $this->createTestSearchStringAnalyzer()->getAnalysisStages('');

        // Assert
        $this->assertSame([], $stages);
    }

    /**
     * Fail-soft path: a real `index_not_found_exception` from Elasticsearch (not a mocked one) must be
     * swallowed, not bubble up as an uncaught exception through to the storefront.
     */
    public function testGetSearchStringTokensReturnsAnEmptyArrayWhenTheIndexDoesNotExist(): void
    {
        // Act
        $tokens = $this->createNonexistentIndexSearchStringAnalyzer()->getTokens('cable');

        // Assert
        $this->assertSame([], $tokens);
    }

    /**
     * Same fail-soft path as above, for the offset-returning sibling method.
     */
    public function testGetTextTokenOffsetsReturnsAnEmptyArrayWhenTheIndexDoesNotExist(): void
    {
        // Act
        $tokenOffsets = $this->createNonexistentIndexSearchStringAnalyzer()->getTokenOffsets('cable');

        // Assert
        $this->assertSame([], $tokenOffsets);
    }
}
