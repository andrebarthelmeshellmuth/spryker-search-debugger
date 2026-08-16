<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Fixture;

use Elastica\Client;
use Elastica\Index;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisStageMapper;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisTreeBuilder;
use SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatter;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzer;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzerInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReader;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;

/**
 * A test-OWNED Elasticsearch index with a small, deliberately stable analysis config — used instead of
 * the host shop's real `page.json` so this package's own test suite doesn't churn every time a shop's
 * search config changes (and so a different shop installing this package can run these same tests
 * unmodified, without needing to match their own `page.json` content). The config here intentionally
 * covers a representative spread of real-world filter shapes: a char filter, boolean filter params
 * (`word_delimiter_graph`), a short list at the truncation boundary (`stop`), and a list well past it
 * (`synonym`) — see `README.md`'s "How it works" for the production `page.json` this was modeled on.
 *
 * Bypasses the real DI `Locator`/`SearchDebugFactory` wiring entirely: the vendor `IndexNameResolver`
 * that wiring would normally produce resolves the index name from the CURRENT STORE and statically
 * caches it for the lifetime of the PHP process (`Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolver::$storeName`)
 * — not safely overridable per-test-class within one Codeception run where another test may have already
 * primed that cache with the real store's name. A fixed-name fake `IndexNameResolverInterface` sidesteps
 * that static cache entirely, so every user of this trait talks to the SAME index regardless of test
 * order.
 */
trait TestPageIndexTrait
{
    /**
     * @var string
     */
    protected const TEST_INDEX_NAME = 'search_debug_test_page';

    /**
     * Never created by {@see createTestPageIndex()} — used by the fail-soft tests that need a REAL
     * "index does not exist" `Elastica\Exception\ResponseException` from a real cluster, not a mocked
     * one, to prove the `catch (ExceptionInterface)` branches genuinely swallow it.
     *
     * @var string
     */
    protected const NONEXISTENT_INDEX_NAME = 'search_debug_test_page_nonexistent';

    protected function createTestPageIndex(): void
    {
        $this->getTestPageIndex()->create(
            [
                'settings' => ['analysis' => $this->getTestPageIndexAnalysisSettings()],
                // Without a mapping, `full-text`'s analyzer/search_analyzer can't be resolved at all —
                // `resolveIndexAnalyzerName()`/`resolveSearchAnalyzerName()` find no matching field and
                // silently fall back to the built-in "standard" analyzer, which has no filter chain of
                // its own to report and makes every richer-pipeline assertion fail in a confusing way
                // (wrong token count, wrong stage count) that looks like a feature bug but isn't one.
                'mappings' => [
                    'properties' => [
                        'full-text' => [
                            'type' => 'text',
                            'analyzer' => 'fulltext_index_analyzer',
                            'search_analyzer' => 'fulltext_search_analyzer',
                        ],
                    ],
                ],
            ],
            ['recreate' => true],
        );
    }

    protected function deleteTestPageIndex(): void
    {
        $index = $this->getTestPageIndex();

        if (!$index->exists()) {
            return;
        }

        $index->delete();
    }

    protected function createTestSearchStringAnalyzer(): SearchStringAnalyzerInterface
    {
        return new SearchStringAnalyzer(
            $this->getTestElasticaClient(),
            $this->createTestIndexNameResolver(),
            $this->createTestIndexSchemaReader(),
            new SearchDebugConfig(),
            new ComponentDefinitionFormatter(),
            new AnalysisTreeBuilder(),
            new AnalysisStageMapper($this->createTestIndexSchemaReader(), new ComponentDefinitionFormatter()),
        );
    }

    protected function createTestIndexSchemaReader(): IndexSchemaReaderInterface
    {
        return new IndexSchemaReader(
            $this->getTestElasticaClient(),
            $this->createTestIndexNameResolver(),
            new IndexSchemaMapper(),
            new SearchDebugConfig(),
        );
    }

    protected function createTestIndexNameResolver(): IndexNameResolverInterface
    {
        return new class (static::TEST_INDEX_NAME) implements IndexNameResolverInterface {
            /**
             * @param string $indexName
             */
            public function __construct(protected string $indexName)
            {
            }

            /**
             * A fixture that always resolves to the one test index, so both arguments are unused by
             * design — the signature is fixed by IndexNameResolverInterface.
             *
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $sourceIdentifier
             * @param string|null $storeName
             */
            public function resolve(string $sourceIdentifier, ?string $storeName = null): string
            {
                return $this->indexName;
            }
        };
    }

    protected function getTestPageIndex(): Index
    {
        return $this->getTestElasticaClient()->getIndex(static::TEST_INDEX_NAME);
    }

    protected function createNonexistentIndexSearchStringAnalyzer(): SearchStringAnalyzerInterface
    {
        return new SearchStringAnalyzer(
            $this->getTestElasticaClient(),
            $this->createNonexistentIndexNameResolver(),
            $this->createNonexistentIndexSchemaReader(),
            new SearchDebugConfig(),
            new ComponentDefinitionFormatter(),
            new AnalysisTreeBuilder(),
            new AnalysisStageMapper($this->createNonexistentIndexSchemaReader(), new ComponentDefinitionFormatter()),
        );
    }

    protected function createNonexistentIndexSchemaReader(): IndexSchemaReaderInterface
    {
        return new IndexSchemaReader(
            $this->getTestElasticaClient(),
            $this->createNonexistentIndexNameResolver(),
            new IndexSchemaMapper(),
            new SearchDebugConfig(),
        );
    }

    protected function createNonexistentIndexNameResolver(): IndexNameResolverInterface
    {
        return new class (static::NONEXISTENT_INDEX_NAME) implements IndexNameResolverInterface {
            /**
             * @param string $indexName
             */
            public function __construct(protected string $indexName)
            {
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $sourceIdentifier
             * @param string|null $storeName
             */
            public function resolve(string $sourceIdentifier, ?string $storeName = null): string
            {
                return $this->indexName;
            }
        };
    }

    /**
     * Same composition `SprykerCommunity\Client\SearchDebug\SearchDebugFactory::getElasticaClient()` uses
     * — both directly-instantiable value objects, no Locator/container needed.
     */
    protected function getTestElasticaClient(): Client
    {
        $searchElasticsearchConfig = new SearchElasticsearchConfig();

        return (new ElasticaClientFactory())->createClient($searchElasticsearchConfig->getClientConfig());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTestPageIndexAnalysisSettings(): array
    {
        return [
            'analyzer' => [
                'fulltext_index_analyzer' => [
                    'tokenizer' => 'standard',
                    'char_filter' => ['unit_symbol_normalizer'],
                    'filter' => [
                        'lowercase',
                        'fulltext_synonyms',
                        'fulltext_word_delimiter',
                        'fulltext_stop_words',
                        'fulltext_min_length',
                        'fulltext_index_ngram_filter',
                    ],
                ],
                'fulltext_search_analyzer' => [
                    'tokenizer' => 'standard',
                    'char_filter' => ['unit_symbol_normalizer'],
                    'filter' => [
                        'lowercase',
                        'fulltext_synonyms',
                        'fulltext_word_delimiter',
                        'fulltext_stop_words',
                        'fulltext_min_length',
                    ],
                ],
            ],
            'char_filter' => [
                'unit_symbol_normalizer' => [
                    'type' => 'mapping',
                    'mappings' => ['µ => u', '& => and'],
                ],
            ],
            'filter' => [
                'fulltext_index_ngram_filter' => [
                    'type' => 'edge_ngram',
                    'min_gram' => 2,
                    'max_gram' => 20,
                ],
                'fulltext_word_delimiter' => [
                    'type' => 'word_delimiter_graph',
                    'generate_word_parts' => true,
                    'catenate_words' => true,
                    'preserve_original' => true,
                ],
                'fulltext_stop_words' => [
                    'type' => 'stop',
                    'stopwords' => ['und', 'oder', 'der', 'die', 'das'],
                ],
                'fulltext_min_length' => [
                    'type' => 'length',
                    'min' => 2,
                ],
                'fulltext_synonyms' => [
                    'type' => 'synonym',
                    'synonyms' => [
                        'screw, bolt => screw, bolt',
                        'battery, accu => battery, accu',
                        'tool, equipment => tool, equipment',
                        'lamp, light => lamp, light',
                        'switch, button => switch, button',
                        'wrench, spanner => wrench, spanner',
                    ],
                ],
            ],
        ];
    }
}
