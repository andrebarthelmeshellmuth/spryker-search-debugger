<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget;

use Spryker\Client\Catalog\CatalogClientInterface;
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Yves\Kernel\AbstractFactory;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalyzeResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalyzeResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\CategoryAncestorNameCollector;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatter;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ProductSourceMapBuilder;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\SkuLookupResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\SkuLookupResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighter;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolverInterface;

class SearchDebugWidgetFactory extends AbstractFactory
{
    public function createSkuLookupResolver(): SkuLookupResolverInterface
    {
        return new SkuLookupResolver(
            $this->getProductStorageClient(),
            $this->getSearchDebugClient(),
            $this->getCatalogClient(),
        );
    }

    public function createAnalyzeResolver(): AnalyzeResolverInterface
    {
        return new AnalyzeResolver(
            $this->getProductStorageClient(),
            $this->getSearchDebugClient(),
            $this->createProductSourceMapBuilder(),
        );
    }

    public function getCatalogClient(): CatalogClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_CATALOG);
    }

    public function createTokenSourceResolver(): TokenSourceResolverInterface
    {
        return new TokenSourceResolver(
            $this->getProductStorageClient(),
            $this->getSearchDebugClient(),
            $this->createTokenHighlighter(),
            $this->createProductSourceMapBuilder(),
        );
    }

    public function createProductSourceMapBuilder(): ProductSourceMapBuilder
    {
        return new ProductSourceMapBuilder(
            $this->getProductStorageClient(),
            $this->getProductCategoryStorageClient(),
            $this->getCategoryStorageClient(),
            $this->getMerchantStorageClient(),
            $this->getStoreClient(),
            $this->createCategoryAncestorNameCollector(),
            $this->getTokenSourceProviderPlugins(),
        );
    }

    public function createCategoryAncestorNameCollector(): CategoryAncestorNameCollector
    {
        return new CategoryAncestorNameCollector();
    }

    public function createAnalysisPathResolver(): AnalysisPathResolverInterface
    {
        return new AnalysisPathResolver($this->getSearchDebugClient(), $this->createTokenHighlighter());
    }

    public function createComponentConfigFormatter(): ComponentConfigFormatterInterface
    {
        return new ComponentConfigFormatter();
    }

    public function createTokenHighlighter(): TokenHighlighterInterface
    {
        return new TokenHighlighter();
    }

    public function getProductStorageClient(): ProductStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_STORAGE);
    }

    public function getProductCategoryStorageClient(): ProductCategoryStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_CATEGORY_STORAGE);
    }

    public function getCategoryStorageClient(): CategoryStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_CATEGORY_STORAGE);
    }

    /**
     * Null on shops without `spryker/merchant-storage` (non-Marketplace) — see
     * {@see SearchDebugWidgetDependencyProvider::addMerchantStorageClient()}.
     */
    public function getMerchantStorageClient(): ?MerchantStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_MERCHANT_STORAGE);
    }

    public function getSearchDebugClient(): SearchDebugClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_SEARCH_DEBUG);
    }

    /**
     * @return array<\SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin\TokenSourceProviderPluginInterface>
     */
    public function getTokenSourceProviderPlugins(): array
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::PLUGINS_TOKEN_SOURCE_PROVIDER);
    }

    public function getStoreClient(): StoreClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_STORE);
    }
}
