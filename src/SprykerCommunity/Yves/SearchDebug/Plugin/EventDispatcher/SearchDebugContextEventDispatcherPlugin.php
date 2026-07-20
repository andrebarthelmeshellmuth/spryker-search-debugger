<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher;

use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\EventDispatcher\EventDispatcherInterface;
use Spryker\Shared\EventDispatcherExtension\Dependency\Plugin\EventDispatcherPluginInterface;
use Spryker\Yves\Kernel\AbstractPlugin;
use Spryker\Yves\Kernel\PermissionAwareTrait;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Marks a request as a search-debug context so the Client-layer query expanders switch `explain` on.
 *
 * This replaces what used to require overriding the shop's own `CatalogController` — the most invasive
 * step of installing this package, and a guaranteed merge conflict for the many shops that already
 * override that controller for their own reasons.
 *
 * It works because the core `CatalogController` builds its search parameters from `$request->query`
 * (`getAllowedRequestParameters()`) and its `reduceRestrictedParameters()` only ever strips *price*
 * parameters from customers lacking `SeePricePermissionPlugin` — it does not whitelist, so a parameter
 * placed on the query bag before the controller runs survives into `catalogSearch()`.
 *
 * Security is unchanged by moving the decision here. The parameter was never the actual gate: it only
 * says "this request is a search-results page", while
 * {@see \SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessChecker} independently
 * re-checks `can(SeeSearchDebugInfoPermissionPlugin::KEY)` in the Client layer before any debug data is
 * produced. A spoofed parameter therefore buys nothing. The unconditional `remove()` below is kept
 * anyway, so a spoofed value can never even reach the (already permission-gated) Client check, and so a
 * permitted customer's own URL cannot smuggle a value the server did not set.
 */
class SearchDebugContextEventDispatcherPlugin extends AbstractPlugin implements EventDispatcherPluginInterface
{
    use PermissionAwareTrait;

    /**
     * @uses \SprykerShop\Yves\CatalogPage\Plugin\Router\CatalogPageRouteProviderPlugin::ROUTE_NAME_SEARCH
     *
     * @var string
     */
    protected const ROUTE_NAME_SEARCH = 'search';

    /**
     * @var string
     */
    protected const REQUEST_ATTRIBUTE_ROUTE = '_route';

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $container is mandated by EventDispatcherPluginInterface.
     *
     * @param \Spryker\Shared\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param \Spryker\Service\Container\ContainerInterface $container
     *
     * @return \Spryker\Shared\EventDispatcher\EventDispatcherInterface
     */
    public function extend(EventDispatcherInterface $eventDispatcher, ContainerInterface $container): EventDispatcherInterface
    {
        $eventDispatcher->addListener(KernelEvents::REQUEST, function (RequestEvent $event): void {
            $this->handleRequest($event);
        });

        return $eventDispatcher;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *
     * @return void
     */
    protected function handleRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Always strip first: whatever the client sent for this parameter is discarded before anything
        // else looks at it, so only a value this plugin sets itself can ever be present.
        $request->query->remove(SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG);

        if (!$this->isSearchResultsPage($request)) {
            return;
        }

        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            return;
        }

        $request->query->set(SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG, true);
    }

    /**
     * Restricted to the full-text search results route on purpose. A category listing page can carry a
     * `q` too, but most category page loads have no query string at all, so enabling debug there would
     * mean paying Elasticsearch's real `explain` cost on page loads that have nothing to explain.
     *
     * Override this method to widen the scope (e.g. to include category pages).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return bool
     */
    protected function isSearchResultsPage(Request $request): bool
    {
        return $request->attributes->get(static::REQUEST_ATTRIBUTE_ROUTE) === static::ROUTE_NAME_SEARCH;
    }
}
