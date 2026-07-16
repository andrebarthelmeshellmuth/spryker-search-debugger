<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget;

use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;
use Spryker\Yves\Kernel\AbstractFactory;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatter;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighter;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolverInterface;

/**
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetConfig getConfig()
 */
class SearchDebugWidgetFactory extends AbstractFactory
{
    /**
     * @return \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolverInterface
     */
    public function createTokenSourceResolver(): TokenSourceResolverInterface
    {
        return new TokenSourceResolver(
            $this->getProductStorageClient(),
            $this->getProductCategoryStorageClient(),
            $this->getCategoryStorageClient(),
            $this->getMerchantStorageClient(),
            $this->getSearchDebugClient(),
            $this->getStoreClient(),
            $this->createTokenHighlighter(),
        );
    }

    /**
     * @return \SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolverInterface
     */
    public function createAnalysisPathResolver(): AnalysisPathResolverInterface
    {
        return new AnalysisPathResolver($this->getSearchDebugClient());
    }

    /**
     * @return \SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatterInterface
     */
    public function createComponentConfigFormatter(): ComponentConfigFormatterInterface
    {
        return new ComponentConfigFormatter();
    }

    /**
     * @return \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface
     */
    public function createTokenHighlighter(): TokenHighlighterInterface
    {
        return new TokenHighlighter();
    }

    /**
     * @return \Spryker\Client\ProductStorage\ProductStorageClientInterface
     */
    public function getProductStorageClient(): ProductStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_STORAGE);
    }

    /**
     * @return \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface
     */
    public function getProductCategoryStorageClient(): ProductCategoryStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_PRODUCT_CATEGORY_STORAGE);
    }

    /**
     * @return \Spryker\Client\CategoryStorage\CategoryStorageClientInterface
     */
    public function getCategoryStorageClient(): CategoryStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_CATEGORY_STORAGE);
    }

    /**
     * @return \Spryker\Client\MerchantStorage\MerchantStorageClientInterface
     */
    public function getMerchantStorageClient(): MerchantStorageClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_MERCHANT_STORAGE);
    }

    /**
     * @return \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface
     */
    public function getSearchDebugClient(): SearchDebugClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_SEARCH_DEBUG);
    }

    /**
     * @return \Spryker\Client\Store\StoreClientInterface
     */
    public function getStoreClient(): StoreClientInterface
    {
        return $this->getProvidedDependency(SearchDebugWidgetDependencyProvider::CLIENT_STORE);
    }
}
