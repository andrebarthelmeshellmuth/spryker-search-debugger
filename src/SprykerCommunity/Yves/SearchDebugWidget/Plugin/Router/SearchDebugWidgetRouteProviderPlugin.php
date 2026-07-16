<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router;

use Spryker\Yves\Router\Plugin\RouteProvider\AbstractRouteProviderPlugin;
use Spryker\Yves\Router\Route\RouteCollection;

class SearchDebugWidgetRouteProviderPlugin extends AbstractRouteProviderPlugin
{
    /**
     * @var string
     */
    public const ROUTE_NAME_TOKEN_SOURCE = 'search-debug/token-source';

    /**
     * @var string
     */
    public const ROUTE_NAME_ANALYSIS_PATH = 'search-debug/token-analysis';

    /**
     * @var string
     */
    public const ROUTE_NAME_COMPONENT_CONFIG = 'search-debug/component-config';

    /**
     * @param \Spryker\Yves\Router\Route\RouteCollection $routeCollection
     *
     * @return \Spryker\Yves\Router\Route\RouteCollection
     */
    public function addRoutes(RouteCollection $routeCollection): RouteCollection
    {
        $route = $this->buildRoute('/search-debug/token-source', 'SearchDebugWidget', 'TokenSource', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_TOKEN_SOURCE, $route);

        $analysisPathRoute = $this->buildRoute('/search-debug/token-analysis', 'SearchDebugWidget', 'AnalysisPath', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_ANALYSIS_PATH, $analysisPathRoute);

        $componentConfigRoute = $this->buildRoute('/search-debug/component-config', 'SearchDebugWidget', 'ComponentConfig', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_COMPONENT_CONFIG, $componentConfigRoute);

        return $routeCollection;
    }
}
