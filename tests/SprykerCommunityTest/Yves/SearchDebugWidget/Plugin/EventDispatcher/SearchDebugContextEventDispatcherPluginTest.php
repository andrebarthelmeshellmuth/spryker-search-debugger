<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Plugin\EventDispatcher;

use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\Shared\EventDispatcher\EventDispatcher;
use Spryker\Shared\EventDispatcher\EventDispatcherInterface;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The permission-granted branch of `handleRequest()` (the only branch that ever SETS the debug
 * parameter) calls `PermissionAwareTrait::can()`, which reaches through the global `Locator` singleton —
 * there is no constructor seam to inject a fake permission client, so that branch is left to integration
 * coverage. Everything reachable without touching `can()` is covered directly below: both early-return
 * paths of `handleRequest()`, plus the route check it depends on.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Plugin
 * @group EventDispatcher
 * @group SearchDebugContextEventDispatcherPluginTest
 * Add your own group annotations below this line
 */
class SearchDebugContextEventDispatcherPluginTest extends Unit
{
    /**
     * @return void
     */
    public function testIsSearchResultsPageReturnsTrueForTheSearchRoute(): void
    {
        // Arrange
        $plugin = new SearchDebugContextEventDispatcherPlugin();
        $request = Request::create('/search');
        $request->attributes->set('_route', 'search');

        // Act
        $result = $this->invokeIsSearchResultsPage($plugin, $request);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * @return void
     */
    public function testIsSearchResultsPageReturnsFalseForAnyOtherRoute(): void
    {
        // Arrange
        $plugin = new SearchDebugContextEventDispatcherPlugin();
        $request = Request::create('/category/cables');
        $request->attributes->set('_route', 'category');

        // Act
        $result = $this->invokeIsSearchResultsPage($plugin, $request);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * A sub-request (e.g. an ESI/fragment render for a widget on the very same search page) must never
     * be treated as the top-level page request — `handleRequest()` returns before it even reaches the
     * request's query bag, so whatever the query bag already held (here: a value this test placed on it)
     * must survive completely untouched.
     *
     * @return void
     */
    public function testHandleRequestDoesNothingForANonMainRequest(): void
    {
        // Arrange
        $plugin = new SearchDebugContextEventDispatcherPlugin();
        $eventDispatcher = $this->extendDispatcher($plugin);

        $request = Request::create('/search?' . SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG . '=1');
        $request->attributes->set('_route', 'search');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        // Act
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);

        // Assert
        $this->assertSame('1', $request->query->get(SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG));
    }

    /**
     * A non-search-results route must end up with the debug parameter stripped and never re-set, even if
     * the client tried to smuggle it in on the query string.
     *
     * @return void
     */
    public function testHandleRequestStripsTheParameterAndReturnsEarlyForANonSearchRoute(): void
    {
        // Arrange
        $plugin = new SearchDebugContextEventDispatcherPlugin();
        $eventDispatcher = $this->extendDispatcher($plugin);

        $request = Request::create('/category/cables?' . SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG . '=1');
        $request->attributes->set('_route', 'category');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        // Act
        $eventDispatcher->dispatch($event, KernelEvents::REQUEST);

        // Assert
        $this->assertFalse($request->query->has(SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG));
    }

    /**
     * @param \SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin $plugin
     *
     * @return \Spryker\Shared\EventDispatcher\EventDispatcherInterface
     */
    protected function extendDispatcher(SearchDebugContextEventDispatcherPlugin $plugin): EventDispatcherInterface
    {
        return $plugin->extend(new EventDispatcher(), $this->createMock(\Spryker\Service\Container\ContainerInterface::class));
    }

    /**
     * @param \SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin $plugin
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return bool
     */
    protected function invokeIsSearchResultsPage(SearchDebugContextEventDispatcherPlugin $plugin, Request $request): bool
    {
        $method = new ReflectionMethod($plugin, 'isSearchResultsPage');
        $method->setAccessible(true);

        return $method->invoke($plugin, $request);
    }
}
