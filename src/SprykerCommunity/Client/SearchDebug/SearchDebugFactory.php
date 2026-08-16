<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug;

use Elastica\Client;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\Permission\PermissionClientInterface;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientBridge;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolver;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use Spryker\Client\SearchElasticsearch\Reader\DocumentReader;
use Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface;
use Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpander;
use Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Service\Synchronization\SynchronizationServiceInterface;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessChecker;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessCheckerInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisStageMapper;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisStageMapperInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisTreeBuilder;
use SprykerCommunity\Client\SearchDebug\Analyzer\AnalysisTreeBuilderInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatter;
use SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzer;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzerInterface;
use SprykerCommunity\Client\SearchDebug\Document\PageDocumentReader;
use SprykerCommunity\Client\SearchDebug\Document\PageDocumentReaderInterface;
use SprykerCommunity\Client\SearchDebug\Explanation\Bm25BreakdownExtractor;
use SprykerCommunity\Client\SearchDebug\Explanation\CrossFieldsSynonymMatcher;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParser;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParserInterface;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReader;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapperInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReader;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;

/**
 * @method \SprykerCommunity\Client\SearchDebug\SearchDebugConfig getConfig()
 */
class SearchDebugFactory extends AbstractFactory
{
    public function createSearchStringAnalyzer(): SearchStringAnalyzerInterface
    {
        return new SearchStringAnalyzer(
            $this->getElasticaClient(),
            $this->createIndexNameResolver(),
            $this->createIndexSchemaReader(),
            $this->getConfig(),
            $this->createComponentDefinitionFormatter(),
            $this->createAnalysisTreeBuilder(),
            $this->createAnalysisStageMapper(),
        );
    }

    public function createComponentDefinitionFormatter(): ComponentDefinitionFormatterInterface
    {
        return new ComponentDefinitionFormatter();
    }

    public function createAnalysisTreeBuilder(): AnalysisTreeBuilderInterface
    {
        return new AnalysisTreeBuilder();
    }

    public function createAnalysisStageMapper(): AnalysisStageMapperInterface
    {
        return new AnalysisStageMapper(
            $this->createIndexSchemaReader(),
            $this->createComponentDefinitionFormatter(),
        );
    }

    public function createIndexSchemaReader(): IndexSchemaReaderInterface
    {
        return new IndexSchemaReader(
            $this->getElasticaClient(),
            $this->createIndexNameResolver(),
            $this->createIndexSchemaMapper(),
            $this->getConfig(),
        );
    }

    public function createIndexSchemaMapper(): IndexSchemaMapperInterface
    {
        return new IndexSchemaMapper();
    }

    public function createPageDocumentReader(): PageDocumentReaderInterface
    {
        return new PageDocumentReader(
            $this->createElasticsearchDocumentReader(),
            $this->createSearchContextExpander(),
            $this->getSynchronizationService(),
            $this->createSearchElasticsearchToStoreClientBridge(),
            $this->getConfig(),
        );
    }

    public function createExplanationParser(): ExplanationParserInterface
    {
        return new ExplanationParser($this->createCrossFieldsSynonymMatcher(), $this->createBm25BreakdownExtractor());
    }

    public function createCrossFieldsSynonymMatcher(): CrossFieldsSynonymMatcher
    {
        return new CrossFieldsSynonymMatcher();
    }

    public function createBm25BreakdownExtractor(): Bm25BreakdownExtractor
    {
        return new Bm25BreakdownExtractor();
    }

    /**
     * @return array<\SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface>
     */
    public function getProductDebugDataExpanderPlugins(): array
    {
        return $this->getProvidedDependency(SearchDebugDependencyProvider::PLUGINS_PRODUCT_DEBUG_DATA_EXPANDER);
    }

    public function createQueryFieldBoostReader(): QueryFieldBoostReaderInterface
    {
        return new QueryFieldBoostReader();
    }

    public function createSearchDebugAccessChecker(): SearchDebugAccessCheckerInterface
    {
        return new SearchDebugAccessChecker($this->getPermissionClient());
    }

    /**
     * COMPOSITION over the core SearchElasticsearch module, deliberately: this module does not extend or
     * override it, so the host project's right to extend `Pyz\Client\SearchElasticsearch` stays untouched.
     * The vendor pieces used here are all public and instantiable; the shared `ElasticaClientFactory`
     * static-caches the client, so this shares the exact connection the shop's own search uses. This is
     * the same composition core itself uses for the identical purpose — see
     * `Spryker\Client\SearchElasticsearch\SearchElasticsearchFactory::getElasticaClient()`, which likewise
     * builds its `ElasticaClientFactory` via its own `createElasticaClientFactory()` method rather than
     * inline, a convention followed here too.
     */
    public function getElasticaClient(): Client
    {
        return $this->createElasticaClientFactory()->createClient(
            $this->createSearchElasticsearchConfig()->getClientConfig(),
        );
    }

    public function createElasticaClientFactory(): ElasticaClientFactory
    {
        return new ElasticaClientFactory();
    }

    /**
     * The vendor module's own config, instantiated for its connection/index settings — the one supported
     * way to obtain them outside that module, since no shared-constant surface exists for the assembled
     * client config array.
     */
    public function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }

    public function createIndexNameResolver(): IndexNameResolverInterface
    {
        return new IndexNameResolver(
            $this->createSearchElasticsearchToStoreClientBridge(),
            $this->createSearchElasticsearchConfig(),
        );
    }

    public function createSearchElasticsearchToStoreClientBridge(): SearchElasticsearchToStoreClientInterface
    {
        return new SearchElasticsearchToStoreClientBridge($this->getStoreClient());
    }

    public function createElasticsearchDocumentReader(): DocumentReaderInterface
    {
        return new DocumentReader($this->getElasticaClient());
    }

    public function createSearchContextExpander(): SearchContextExpanderInterface
    {
        return new SearchContextExpander($this->createIndexNameResolver());
    }

    public function getPermissionClient(): PermissionClientInterface
    {
        return $this->getProvidedDependency(SearchDebugDependencyProvider::CLIENT_PERMISSION);
    }

    public function getSynchronizationService(): SynchronizationServiceInterface
    {
        return $this->getProvidedDependency(SearchDebugDependencyProvider::SERVICE_SYNCHRONIZATION);
    }

    public function getStoreClient(): StoreClientInterface
    {
        return $this->getProvidedDependency(SearchDebugDependencyProvider::CLIENT_STORE);
    }
}
