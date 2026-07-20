<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget;

use Spryker\Client\MerchantStorage\MerchantStorageClient;
use Spryker\Yves\Kernel\AbstractBundleDependencyProvider;
use Spryker\Yves\Kernel\Container;

class SearchDebugWidgetDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_PRODUCT_STORAGE = 'CLIENT_PRODUCT_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_PRODUCT_CATEGORY_STORAGE = 'CLIENT_PRODUCT_CATEGORY_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_CATEGORY_STORAGE = 'CLIENT_CATEGORY_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_MERCHANT_STORAGE = 'CLIENT_MERCHANT_STORAGE';

    /**
     * @var string
     */
    public const CLIENT_SEARCH_DEBUG = 'CLIENT_SEARCH_DEBUG';

    /**
     * @var string
     */
    public const CLIENT_STORE = 'CLIENT_STORE';

    /**
     * @var string
     */
    public const PLUGINS_TOKEN_SOURCE_PROVIDER = 'PLUGINS_TOKEN_SOURCE_PROVIDER';

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    public function provideDependencies(Container $container): Container
    {
        $container = parent::provideDependencies($container);
        $container = $this->addProductStorageClient($container);
        $container = $this->addProductCategoryStorageClient($container);
        $container = $this->addCategoryStorageClient($container);
        $container = $this->addMerchantStorageClient($container);
        $container = $this->addSearchDebugClient($container);
        $container = $this->addStoreClient($container);
        $container = $this->addTokenSourceProviderPlugins($container);

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addProductStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_PRODUCT_STORAGE, function (Container $container) {
            return $container->getLocator()->productStorage()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addProductCategoryStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_PRODUCT_CATEGORY_STORAGE, function (Container $container) {
            return $container->getLocator()->productCategoryStorage()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addCategoryStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_CATEGORY_STORAGE, function (Container $container) {
            return $container->getLocator()->categoryStorage()->client();
        });

        return $container;
    }

    /**
     * Resolves to null on any shop without `spryker/merchant-storage` installed. Merchant names are a
     * Marketplace-only concept, so requiring that module would force every plain B2B/B2C shop to install
     * a Marketplace subsystem — `spryker/merchant-storage` pulls in `spryker/merchant`, publish/sync
     * infrastructure and Propel tables — purely to satisfy one optional attribution line on the
     * token-source page. The module is therefore a composer `suggest`, not a `require`, and its absence is
     * detected here rather than allowed to fatal inside the service locator.
     *
     * `class_exists()` is the detection mechanism deliberately: the Yves locator resolves module names
     * dynamically at call time, so asking it for a module that isn't installed throws rather than
     * returning null — there is nothing to null-check without probing first.
     *
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addMerchantStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_MERCHANT_STORAGE, function (Container $container) {
            if (!class_exists(MerchantStorageClient::class)) {
                return null;
            }

            return $container->getLocator()->merchantStorage()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addSearchDebugClient(Container $container): Container
    {
        $container->set(static::CLIENT_SEARCH_DEBUG, function (Container $container) {
            return $container->getLocator()->searchDebug()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addStoreClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORE, function (Container $container) {
            return $container->getLocator()->store()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addTokenSourceProviderPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_TOKEN_SOURCE_PROVIDER, function () {
            return $this->getTokenSourceProviderPlugins();
        });

        return $container;
    }

    /**
     * Override on project level to name the origin of values your own indexing contributes, so the
     * token-source page can label them instead of falling back to "other indexed value". Empty by
     * default: this package cannot know what a project's own map-expander plugins put into the index.
     *
     * @return array<\SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin\TokenSourceProviderPluginInterface>
     */
    protected function getTokenSourceProviderPlugins(): array
    {
        return [];
    }
}
