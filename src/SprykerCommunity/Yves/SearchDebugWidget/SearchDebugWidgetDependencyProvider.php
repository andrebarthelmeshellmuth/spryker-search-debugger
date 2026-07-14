<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget;

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
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function addMerchantStorageClient(Container $container): Container
    {
        $container->set(static::CLIENT_MERCHANT_STORAGE, function (Container $container) {
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
}
