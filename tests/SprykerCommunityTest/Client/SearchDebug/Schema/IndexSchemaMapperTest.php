<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Schema;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchElasticsearch
 * @group Schema
 * @group IndexSchemaMapperTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class IndexSchemaMapperTest extends Unit
{
    /**
     * The resolution rules are Elasticsearch's own: explicitly configured analyzers are taken verbatim.
     */
    public function testMapResolvesExplicitlyConfiguredAnalyzers(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'full-text' => [
                    'type' => 'text',
                    'analyzer' => 'fulltext_index_analyzer',
                    'search_analyzer' => 'fulltext_search_analyzer',
                ],
            ],
        ];

        // Act
        $searchIndexSchemaTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('spryker_de_page', $mapping, []);

        // Assert
        $this->assertSame('spryker_de_page', $searchIndexSchemaTransfer->getIndexName());
        $searchIndexFieldTransfer = $searchIndexSchemaTransfer->getFields()[0];
        $this->assertSame('full-text', $searchIndexFieldTransfer->getName());
        $this->assertSame('fulltext_index_analyzer', $searchIndexFieldTransfer->getAnalyzerName());
        $this->assertSame('fulltext_search_analyzer', $searchIndexFieldTransfer->getSearchAnalyzerName());
    }

    /**
     * No `search_analyzer` configured → Elasticsearch queries with the field's index analyzer.
     */
    public function testMapFallsBackToTheIndexAnalyzerAsSearchAnalyzer(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'full-text' => ['type' => 'text', 'analyzer' => 'my_analyzer'],
            ],
        ];

        // Act
        $searchIndexFieldTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', $mapping, [])
            ->getFields()[0];

        // Assert
        $this->assertSame('my_analyzer', $searchIndexFieldTransfer->getAnalyzerName());
        $this->assertSame('my_analyzer', $searchIndexFieldTransfer->getSearchAnalyzerName());
    }

    /**
     * A text field with no analyzer configuration at all is analyzed with "standard" — the vanilla core
     * `page.json` case, where `full-text` is a plain text field.
     */
    public function testMapFallsBackToTheStandardAnalyzerForPlainTextFields(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'full-text' => ['type' => 'text'],
            ],
        ];

        // Act
        $searchIndexFieldTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', $mapping, [])
            ->getFields()[0];

        // Assert
        $this->assertSame(IndexSchemaMapper::DEFAULT_ANALYZER_NAME, $searchIndexFieldTransfer->getAnalyzerName());
        $this->assertSame(IndexSchemaMapper::DEFAULT_ANALYZER_NAME, $searchIndexFieldTransfer->getSearchAnalyzerName());
    }

    /**
     * Non-text fields are not analyzed — they must not report analyzer names, "standard" would be a lie.
     */
    public function testMapLeavesNonTextFieldsWithoutAnalyzerNames(): void
    {
        // Arrange
        $mapping = [
            'properties' => [
                'integer-facet' => ['type' => 'nested'],
            ],
        ];

        // Act
        $searchIndexFieldTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', $mapping, [])
            ->getFields()[0];

        // Assert
        $this->assertSame('nested', $searchIndexFieldTransfer->getType());
        $this->assertNull($searchIndexFieldTransfer->getAnalyzerName());
        $this->assertNull($searchIndexFieldTransfer->getSearchAnalyzerName());
    }

    /**
     * Custom analyzer definitions from the settings come along verbatim — tokenizer and filter chain in
     * configured order, the raw material for the upcoming analysis-pipeline display.
     */
    public function testMapCarriesAnalyzerDefinitionsFromTheAnalysisSettings(): void
    {
        // Arrange
        $analysisSettings = [
            'analyzer' => [
                'fulltext_index_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'fulltext_index_ngram_filter'],
                ],
            ],
        ];

        // Act
        $searchAnalyzerTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', [], $analysisSettings)
            ->getAnalyzers()[0];

        // Assert
        $this->assertSame('fulltext_index_analyzer', $searchAnalyzerTransfer->getName());
        $this->assertSame('standard', $searchAnalyzerTransfer->getTokenizerName());
        $this->assertSame(['lowercase', 'fulltext_index_ngram_filter'], $searchAnalyzerTransfer->getFilterNames());
        $this->assertSame([], $searchAnalyzerTransfer->getCharFilterNames());
    }

    /**
     * The real `page.json` shape for this shop's one custom filter — `type` plus type-specific config
     * keys, verbatim.
     */
    public function testMapCarriesFilterDefinitionsFromTheAnalysisSettings(): void
    {
        // Arrange
        $analysisSettings = [
            'filter' => [
                'fulltext_index_ngram_filter' => [
                    'type' => 'edge_ngram',
                    'min_gram' => 2,
                    'max_gram' => 20,
                ],
            ],
        ];

        // Act
        $filterTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', [], $analysisSettings)
            ->getFilters()[0];

        // Assert
        $this->assertSame('fulltext_index_ngram_filter', $filterTransfer->getName());
        $this->assertSame('edge_ngram', $filterTransfer->getType());
        $this->assertSame(['min_gram' => 2, 'max_gram' => 20], $filterTransfer->getConfig());
    }

    /**
     * Same shape, different settings key — tokenizer and char_filter definitions map identically to
     * filter definitions (all three are `{"type": "...", ...}` blocks in Elasticsearch's analysis
     * settings), so one assertion per key is enough to cover the shared mapping code.
     */
    public function testMapCarriesTokenizerAndCharFilterDefinitionsFromTheAnalysisSettings(): void
    {
        // Arrange
        $analysisSettings = [
            'tokenizer' => [
                'my_tokenizer' => ['type' => 'ngram', 'min_gram' => 3, 'max_gram' => 4],
            ],
            'char_filter' => [
                'my_char_filter' => ['type' => 'html_strip'],
            ],
        ];

        // Act
        $searchIndexSchemaTransfer = (new IndexSchemaMapper())
            ->mapToSearchIndexSchemaTransfer('idx', [], $analysisSettings);

        // Assert
        $tokenizerTransfer = $searchIndexSchemaTransfer->getTokenizers()[0];
        $this->assertSame('my_tokenizer', $tokenizerTransfer->getName());
        $this->assertSame('ngram', $tokenizerTransfer->getType());
        $this->assertSame(['min_gram' => 3, 'max_gram' => 4], $tokenizerTransfer->getConfig());

        $charFilterTransfer = $searchIndexSchemaTransfer->getCharFilters()[0];
        $this->assertSame('my_char_filter', $charFilterTransfer->getName());
        $this->assertSame('html_strip', $charFilterTransfer->getType());
        $this->assertSame([], $charFilterTransfer->getConfig());
    }
}
