<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug;

use Codeception\Test\Unit;
use Elastica\Client as ElasticaClient;
use Spryker\Client\Kernel\Container;
use Spryker\Client\Permission\PermissionClientInterface;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface;
use Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface;
use Spryker\Client\SearchElasticsearch\SearchElasticsearchConfig;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Service\Synchronization\SynchronizationServiceInterface;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessCheckerInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzerInterface;
use SprykerCommunity\Client\SearchDebug\Document\PageDocumentReaderInterface;
use SprykerCommunity\Client\SearchDebug\Explanation\ExplanationParserInterface;
use SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapperInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Client\SearchDebug\SearchDebugDependencyProvider;
use SprykerCommunity\Client\SearchDebug\SearchDebugFactory;

/**
 * Smoke tests, one per `create*()` method: every method is called and the return type is asserted, nothing
 * more. Cheap insurance against a wrong constructor-argument count/order — exactly the kind of bug a
 * `phpstan`/IDE pass alone won't always catch when a constructor changes but a caller isn't updated (see
 * `SearchDebugWidgetFactory::createAnalysisPathResolver()`'s history for a real example of this).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group SearchDebugFactoryTest
 * Add your own group annotations below this line
 */
class SearchDebugFactoryTest extends Unit
{
    /**
     * @return void
     */
    public function testCreateSearchStringAnalyzerReturnsASearchStringAnalyzer(): void
    {
        $this->assertInstanceOf(SearchStringAnalyzerInterface::class, $this->createFactory()->createSearchStringAnalyzer());
    }

    /**
     * @return void
     */
    public function testCreateComponentDefinitionFormatterReturnsAComponentDefinitionFormatter(): void
    {
        $this->assertInstanceOf(ComponentDefinitionFormatterInterface::class, $this->createFactory()->createComponentDefinitionFormatter());
    }

    /**
     * @return void
     */
    public function testCreateIndexSchemaReaderReturnsAnIndexSchemaReader(): void
    {
        $this->assertInstanceOf(IndexSchemaReaderInterface::class, $this->createFactory()->createIndexSchemaReader());
    }

    /**
     * @return void
     */
    public function testCreateIndexSchemaMapperReturnsAnIndexSchemaMapper(): void
    {
        $this->assertInstanceOf(IndexSchemaMapperInterface::class, $this->createFactory()->createIndexSchemaMapper());
    }

    /**
     * @return void
     */
    public function testCreatePageDocumentReaderReturnsAPageDocumentReader(): void
    {
        $this->assertInstanceOf(PageDocumentReaderInterface::class, $this->createFactory()->createPageDocumentReader());
    }

    /**
     * @return void
     */
    public function testCreateExplanationParserReturnsAnExplanationParser(): void
    {
        $this->assertInstanceOf(ExplanationParserInterface::class, $this->createFactory()->createExplanationParser());
    }

    /**
     * @return void
     */
    public function testCreateQueryFieldBoostReaderReturnsAQueryFieldBoostReader(): void
    {
        $this->assertInstanceOf(QueryFieldBoostReaderInterface::class, $this->createFactory()->createQueryFieldBoostReader());
    }

    /**
     * @return void
     */
    public function testCreateSearchDebugAccessCheckerReturnsASearchDebugAccessChecker(): void
    {
        $this->assertInstanceOf(SearchDebugAccessCheckerInterface::class, $this->createFactory()->createSearchDebugAccessChecker());
    }

    /**
     * @return void
     */
    public function testGetElasticaClientReturnsAnElasticaClient(): void
    {
        $this->assertInstanceOf(ElasticaClient::class, $this->createFactory()->getElasticaClient());
    }

    /**
     * @return void
     */
    public function testCreateElasticaClientFactoryReturnsAnElasticaClientFactory(): void
    {
        $this->assertInstanceOf(ElasticaClientFactory::class, $this->createFactory()->createElasticaClientFactory());
    }

    /**
     * @return void
     */
    public function testCreateSearchElasticsearchConfigReturnsASearchElasticsearchConfig(): void
    {
        $this->assertInstanceOf(SearchElasticsearchConfig::class, $this->createFactory()->createSearchElasticsearchConfig());
    }

    /**
     * @return void
     */
    public function testCreateIndexNameResolverReturnsAnIndexNameResolver(): void
    {
        $this->assertInstanceOf(IndexNameResolverInterface::class, $this->createFactory()->createIndexNameResolver());
    }

    /**
     * @return void
     */
    public function testCreateSearchElasticsearchToStoreClientBridgeReturnsABridge(): void
    {
        $this->assertInstanceOf(
            SearchElasticsearchToStoreClientInterface::class,
            $this->createFactory()->createSearchElasticsearchToStoreClientBridge(),
        );
    }

    /**
     * @return void
     */
    public function testCreateElasticsearchDocumentReaderReturnsADocumentReader(): void
    {
        $this->assertInstanceOf(DocumentReaderInterface::class, $this->createFactory()->createElasticsearchDocumentReader());
    }

    /**
     * @return void
     */
    public function testCreateSearchContextExpanderReturnsASearchContextExpander(): void
    {
        $this->assertInstanceOf(SearchContextExpanderInterface::class, $this->createFactory()->createSearchContextExpander());
    }

    /**
     * Builds a real factory wired to a container carrying only test-double leaf dependencies (store,
     * permission, synchronization clients/services) — everything this package composes itself (the
     * Elastica client, the vendor SearchElasticsearch pieces) is built for real, since those are plain,
     * side-effect-free value objects (see `SearchDebugFactory::getElasticaClient()`'s own docblock).
     *
     * @return \SprykerCommunity\Client\SearchDebug\SearchDebugFactory
     */
    protected function createFactory(): SearchDebugFactory
    {
        $container = new Container();
        $container->set(SearchDebugDependencyProvider::CLIENT_STORE, $this->createMock(StoreClientInterface::class));
        $container->set(SearchDebugDependencyProvider::CLIENT_PERMISSION, $this->createMock(PermissionClientInterface::class));
        $container->set(SearchDebugDependencyProvider::SERVICE_SYNCHRONIZATION, $this->createMock(SynchronizationServiceInterface::class));
        $container->set(SearchDebugDependencyProvider::PLUGINS_PRODUCT_DEBUG_DATA_EXPANDER, []);

        $factory = new SearchDebugFactory();
        $factory->setContainer($container);
        $factory->setConfig(new SearchDebugConfig());

        return $factory;
    }
}
