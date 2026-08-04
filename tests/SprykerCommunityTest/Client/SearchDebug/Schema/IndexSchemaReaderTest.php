<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Schema;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunityTest\Client\SearchDebug\Fixture\TestPageIndexTrait;

/**
 * INTEGRATION TEST — talks to a real Elasticsearch, against a TEST-OWNED index (`TestPageIndexTrait`),
 * not the host shop's real `page.json`/live index — see that trait's own docblock for why. A wrong
 * component-kind lookup wouldn't throw here, `findComponent()` would just silently return null, so a
 * real round-trip against a real `_settings` response stays essential; it just no longer needs to be the
 * SHOP's live index to get that guarantee.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchElasticsearch
 * @group Schema
 * @group IndexSchemaReaderTest
 * Add your own group annotations below this line
 */
class IndexSchemaReaderTest extends Unit
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
     * Note `min_gram`/`max_gram` come back as STRINGS ("2"/"20"), not integers — confirmed live:
     * Elasticsearch's settings API normalizes numeric settings values to strings, even though the
     * fixture's config declares them as PHP integers. Harmless for display (`ComponentDefinitionFormatter`
     * casts either shape to the same string), but this test's own real-data grounding is exactly why it
     * caught it — an invented fixture asserting native ints would have "confirmed" the wrong shape.
     */
    public function testFindComponentReturnsTheFullUntruncatedConfigForANamedFilter(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_FILTER, 'fulltext_index_ngram_filter');

        // Assert
        $this->assertNotNull($component);
        $this->assertSame('fulltext_index_ngram_filter', $component->getName());
        $this->assertSame('edge_ngram', $component->getType());
        $this->assertSame(['min_gram' => '2', 'max_gram' => '20'], $component->getConfig());
    }

    /**
     * Same live-confirmed string-normalization as above, this time for the `stop` filter's SHORT list —
     * exactly at `ComponentDefinitionFormatter`'s 5-item preview limit, so it's the boundary case for
     * "shown in full, not truncated".
     */
    public function testFindComponentReturnsAShortListFilterInFull(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_FILTER, 'fulltext_stop_words');

        // Assert
        $this->assertNotNull($component);
        $this->assertSame('stop', $component->getType());
        $this->assertSame(['stopwords' => ['und', 'oder', 'der', 'die', 'das']], $component->getConfig());
    }

    /**
     * The `synonym` filter's list is deliberately past the 5-item preview limit — this is the config
     * that makes a real `token-analysis` page stage's `definitionTruncated` come back true.
     */
    public function testFindComponentReturnsALongListFilterInFullEvenThoughItWouldBeTruncatedForDisplay(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_FILTER, 'fulltext_synonyms');

        // Assert
        $this->assertNotNull($component);
        $this->assertSame('synonym', $component->getType());
        $this->assertCount(6, $component->getConfig()['synonyms']);
    }

    /**
     * `word_delimiter_graph`'s boolean flags — confirmed live that Elasticsearch's settings API reports
     * these as the STRINGS `"true"`/`"false"`, not native JSON booleans, same normalization behavior as
     * the numeric `min_gram`/`max_gram` above.
     *
     * `assertEquals`, not `assertSame`: this config is a JSON OBJECT (key => value), not a list, and
     * Elasticsearch does not guarantee returning its keys in the same order they were declared in —
     * confirmed live, where it came back reordered from this fixture's own declaration order. Real key
     * order across a settings round-trip isn't semantically meaningful here, only the key => value pairs
     * are, so an order-sensitive `===` comparison would be asserting something this test has no actual
     * interest in.
     */
    public function testFindComponentReturnsBooleanConfigValuesAsStrings(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_FILTER, 'fulltext_word_delimiter');

        // Assert
        $this->assertNotNull($component);
        $this->assertSame('word_delimiter_graph', $component->getType());
        $this->assertEquals(
            ['generate_word_parts' => 'true', 'catenate_words' => 'true', 'preserve_original' => 'true'],
            $component->getConfig(),
        );
    }

    /**
     * The char filter path — real char filters run before tokenization, on the raw character stream, and
     * are looked up through a separate `analysisSettings['char_filter']` block from tokenizer/filter.
     */
    public function testFindComponentReturnsACharFilterDefinition(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_CHAR_FILTER, 'unit_symbol_normalizer');

        // Assert
        $this->assertNotNull($component);
        $this->assertSame('mapping', $component->getType());
        $this->assertSame(['mappings' => ['µ => u', '& => and']], $component->getConfig());
    }

    /**
     * "lowercase" is used by name only in the fixture's filter chain, never customized — there is no
     * entry for it in the analysis settings' `filter` block at all.
     */
    public function testFindComponentReturnsNullForABuiltInComponentUsedByNameOnly(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent(IndexSchemaMapper::COMPONENT_KIND_FILTER, 'lowercase');

        // Assert
        $this->assertNull($component);
    }

    public function testFindComponentReturnsNullForAnUnrecognizedComponentKind(): void
    {
        // Act
        $component = $this->createTestIndexSchemaReader()->findComponent('not-a-real-kind', 'fulltext_index_ngram_filter');

        // Assert
        $this->assertNull($component);
    }

    /**
     * Fail-soft path: a real `index_not_found_exception` from Elasticsearch (not a mocked one) must be
     * swallowed, returning an empty (but named) schema so callers degrade to the "standard" analyzer
     * instead of throwing — the same posture `SearchStringAnalyzer`'s own `_analyze` calls take.
     */
    public function testGetPageIndexSchemaReturnsAnEmptyNamedSchemaWhenTheIndexDoesNotExist(): void
    {
        // Act
        $schema = $this->createNonexistentIndexSchemaReader()->getPageIndexSchema();

        // Assert
        $this->assertSame(static::NONEXISTENT_INDEX_NAME, $schema->getIndexName());
        $this->assertSame([], $schema->getFields()->getArrayCopy());
    }
}
