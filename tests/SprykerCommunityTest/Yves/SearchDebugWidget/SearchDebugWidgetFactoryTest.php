<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget;

use Codeception\Test\Unit;
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Yves\Kernel\Container;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetDependencyProvider;
use SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory;

/**
 * Smoke tests, one per `create*()` method: every method is called and the return type is asserted, nothing
 * more. This is what would have caught `createAnalysisPathResolver()` calling `new AnalysisPathResolver()`
 * with only one constructor argument after the constructor grew a second one — a bug that instead surfaced
 * as 8 unrelated downstream test failures before being traced back here.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group SearchDebugWidgetFactoryTest
 * Add your own group annotations below this line
 */
class SearchDebugWidgetFactoryTest extends Unit
{
    /**
     * @return void
     */
    public function testCreateTokenSourceResolverReturnsATokenSourceResolver(): void
    {
        $this->assertInstanceOf(TokenSourceResolverInterface::class, $this->createFactory()->createTokenSourceResolver());
    }

    /**
     * @return void
     */
    public function testCreateAnalysisPathResolverReturnsAnAnalysisPathResolver(): void
    {
        $this->assertInstanceOf(AnalysisPathResolverInterface::class, $this->createFactory()->createAnalysisPathResolver());
    }

    /**
     * @return void
     */
    public function testCreateComponentConfigFormatterReturnsAComponentConfigFormatter(): void
    {
        $this->assertInstanceOf(ComponentConfigFormatterInterface::class, $this->createFactory()->createComponentConfigFormatter());
    }

    /**
     * @return void
     */
    public function testCreateTokenHighlighterReturnsATokenHighlighter(): void
    {
        $this->assertInstanceOf(TokenHighlighterInterface::class, $this->createFactory()->createTokenHighlighter());
    }

    /**
     * @return \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory
     */
    protected function createFactory(): SearchDebugWidgetFactory
    {
        $container = new Container();
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_STORAGE, $this->createMock(ProductStorageClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_CATEGORY_STORAGE, $this->createMock(ProductCategoryStorageClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_CATEGORY_STORAGE, $this->createMock(CategoryStorageClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_MERCHANT_STORAGE, $this->createMock(MerchantStorageClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_SEARCH_DEBUG, $this->createMock(SearchDebugClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::CLIENT_STORE, $this->createMock(StoreClientInterface::class));
        $container->set(SearchDebugWidgetDependencyProvider::PLUGINS_TOKEN_SOURCE_PROVIDER, []);

        $factory = new SearchDebugWidgetFactory();
        $factory->setContainer($container);

        return $factory;
    }
}
