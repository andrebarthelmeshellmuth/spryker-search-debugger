<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router;

use Spryker\Shared\Config\Config;
use Spryker\Yves\Router\Plugin\RouteProvider\AbstractRouteProviderPlugin;
use Spryker\Yves\Router\Route\RouteCollection;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConstants;

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
     * @var string
     */
    public const ROUTE_NAME_CHECK_INSTALLATION = 'search-debug/check-installation';

    /**
     * @param \Spryker\Yves\Router\Route\RouteCollection $routeCollection
     */
    public function addRoutes(RouteCollection $routeCollection): RouteCollection
    {
        $route = $this->buildRoute('/search-debug/token-source', 'SearchDebugWidget', 'TokenSource', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_TOKEN_SOURCE, $route);

        $analysisPathRoute = $this->buildRoute('/search-debug/token-analysis', 'SearchDebugWidget', 'AnalysisPath', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_ANALYSIS_PATH, $analysisPathRoute);

        $componentConfigRoute = $this->buildRoute('/search-debug/component-config', 'SearchDebugWidget', 'ComponentConfig', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_COMPONENT_CONFIG, $componentConfigRoute);

        $this->addCheckInstallationRoute($routeCollection);

        return $routeCollection;
    }

    /**
     * Only registered when {@see SearchDebugConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED} allows it
     * (default: no) — a project opts in via its development-tier config, so unless that flag is explicitly
     * set, this route never exists and the URL 404s exactly like any nonexistent path, rather than
     * existing-but-denied. See that constant for why a runtime permission check alone would not be enough.
     *
     * @param \Spryker\Yves\Router\Route\RouteCollection $routeCollection
     */
    protected function addCheckInstallationRoute(RouteCollection $routeCollection): void
    {
        if (!Config::get(SearchDebugConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED, false)) {
            return;
        }

        $checkInstallationRoute = $this->buildRoute('/search-debug/check-installation', 'SearchDebugWidget', 'CheckInstallation', 'indexAction');
        $routeCollection->add(static::ROUTE_NAME_CHECK_INSTALLATION, $checkInstallationRoute);
    }
}
