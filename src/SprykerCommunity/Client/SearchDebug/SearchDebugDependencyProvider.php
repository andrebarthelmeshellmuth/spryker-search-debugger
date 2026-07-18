<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug;

use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;

class SearchDebugDependencyProvider extends AbstractDependencyProvider
{
    /**
     * @var string
     */
    public const CLIENT_STORE = 'CLIENT_STORE';

    /**
     * @var string
     */
    public const CLIENT_PERMISSION = 'CLIENT_PERMISSION';

    /**
     * @var string
     */
    public const SERVICE_SYNCHRONIZATION = 'SERVICE_SYNCHRONIZATION';

    /**
     * @var string
     */
    public const PLUGINS_PRODUCT_DEBUG_DATA_EXPANDER = 'PLUGINS_PRODUCT_DEBUG_DATA_EXPANDER';

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    public function provideServiceLayerDependencies(Container $container): Container
    {
        $container = parent::provideServiceLayerDependencies($container);
        $container = $this->addStoreClient($container);
        $container = $this->addPermissionClient($container);
        $container = $this->addSynchronizationService($container);
        $container = $this->addProductDebugDataExpanderPlugins($container);

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addProductDebugDataExpanderPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_PRODUCT_DEBUG_DATA_EXPANDER, function () {
            return $this->getProductDebugDataExpanderPlugins();
        });

        return $container;
    }

    /**
     * Override on project level to plug additional per-product overlay sections in — e.g.
     * spryker-community/search-ranking's business-signal breakdown.
     *
     * @return array<\SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface>
     */
    protected function getProductDebugDataExpanderPlugins(): array
    {
        return [];
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addStoreClient(Container $container): Container
    {
        $container->set(static::CLIENT_STORE, function (Container $container) {
            return $container->getLocator()->store()->client();
        });

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addPermissionClient(Container $container): Container
    {
        $container->set(static::CLIENT_PERMISSION, function (Container $container) {
            return $container->getLocator()->permission()->client();
        });

        return $container;
    }

    /**
     * The synchronization service owns the document-key format shared by the publish pipeline and the
     * search/storage backends — it is the authority on how an entity's search document id is built.
     *
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addSynchronizationService(Container $container): Container
    {
        $container->set(static::SERVICE_SYNCHRONIZATION, function (Container $container) {
            return $container->getLocator()->synchronization()->service();
        });

        return $container;
    }
}
