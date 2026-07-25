<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Controller;

use Closure;
use ReflectionFunction;
use Spryker\Yves\Kernel\Controller\AbstractController;
use Spryker\Yves\Kernel\PermissionAwareTrait;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin;
use SprykerCommunity\Yves\SearchDebug\Plugin\Twig\SearchDebugTwigPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router\SearchDebugWidgetRouteProviderPlugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Twig\Error\SyntaxError;

/**
 * Diagnoses the Yves-side half of a search-debug installation — the half
 * {@see \SprykerCommunity\Zed\SearchDebug\Communication\Console\SearchDebugCheckInstallationConsole}
 * explicitly cannot reach, because Zed never bootstraps the Yves DI container. Complementary to that
 * console command, not a replacement for it: this page does not re-check engine reachability, the page
 * index, or explain support — run the console command for those.
 *
 * Reachable only when BOTH gates pass: the route itself only exists when
 * {@see \SprykerCommunity\Shared\SearchDebug\SearchDebugConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED}
 * allows it (defaults to `false` — a project opts in via its development-tier config, so the URL 404s
 * everywhere else regardless of permission — see that constant for why), AND the visiting customer holds
 * {@see SeeSearchDebugInfoPermissionPlugin}. Missing the permission on an environment where the route
 * does exist renders a dedicated explanation with the exact remedy, rather than a bare 403 — a customer
 * lacking the permission is not a security incident here, it is almost always someone mid-setup who
 * has not granted it yet, so it does not warrant the exact same anonymous non-response an unflagged
 * environment gets.
 *
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory getFactory()
 */
class CheckInstallationController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * @uses \Spryker\Yves\EventDispatcher\Plugin\Application\EventDispatcherApplicationPlugin::SERVICE_DISPATCHER
     *
     * @var string
     */
    protected const SERVICE_DISPATCHER = 'dispatcher';

    /**
     * @return \Spryker\Yves\Kernel\View\View|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            return $this->renderView(
                '@SearchDebugWidget/views/check-installation/permission-denied.twig',
                [],
                new Response('', Response::HTTP_FORBIDDEN),
            );
        }

        return $this->view(
            [
                'checks' => $this->runChecks(),
            ],
            [],
            '@SearchDebugWidget/views/check-installation/check-installation.twig',
        );
    }

    /**
     * @return array<int, array{label: string, passed: bool, remedy: string|null}>
     */
    protected function runChecks(): array
    {
        return [
            $this->checkTwigFunction(),
            $this->checkEventListener(),
            $this->checkRoutes(),
        ];
    }

    /**
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkTwigFunction(): array
    {
        $isRegistered = $this->isTwigFunctionCallable(SearchDebugTwigPlugin::FUNCTION_NAME_TOKEN_COLORS);

        return [
            'label' => 'Twig helper function "searchDebugTokenColors" is registered',
            'passed' => $isRegistered,
            'remedy' => $isRegistered
                ? null
                : 'Register SearchDebugTwigPlugin in src/Pyz/Yves/Twig/TwigDependencyProvider.php (see README step 5).',
        ];
    }

    /**
     * Compiles a throwaway one-line template that calls the function, rather than inspecting
     * `Twig\Environment`'s function registry directly — that registry is only reachable through
     * `getFunction()`, which Twig marks `@internal`. `createTemplate()` is Twig's own documented,
     * non-internal way to ask "does this compile", and it already throws {@see SyntaxError} for an
     * unknown function at compile time (see its own `@throws` docblock), so no render is needed either.
     *
     * @param string $functionName
     *
     * @return bool
     */
    protected function isTwigFunctionCallable(string $functionName): bool
    {
        try {
            $this->getTwig()->createTemplate(sprintf('{{ %s([]) }}', $functionName));

            return true;
        } catch (SyntaxError) {
            return false;
        }
    }

    /**
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkEventListener(): array
    {
        $eventDispatcher = $this->getApplication()->get(static::SERVICE_DISPATCHER);
        $isRegistered = $this->isListenerBound($eventDispatcher, KernelEvents::REQUEST, SearchDebugContextEventDispatcherPlugin::class);

        return [
            'label' => 'Search-results event listener (SearchDebugContextEventDispatcherPlugin) is registered',
            'passed' => $isRegistered,
            'remedy' => $isRegistered
                ? null
                : 'Register SearchDebugContextEventDispatcherPlugin in src/Pyz/Yves/EventDispatcher/EventDispatcherDependencyProvider.php (see README step 5).',
        ];
    }

    /**
     * @return array{label: string, passed: bool, remedy: string|null}
     */
    protected function checkRoutes(): array
    {
        $missingRouteNames = [];

        foreach ($this->getWidgetRouteNames() as $routeName) {
            if ($this->isRouteRegistered($routeName)) {
                continue;
            }

            $missingRouteNames[] = $routeName;
        }

        return [
            'label' => 'Token-source, analysis-path and component-config routes are registered',
            'passed' => $missingRouteNames === [],
            'remedy' => $missingRouteNames === []
                ? null
                : sprintf(
                    'Register SearchDebugWidgetRouteProviderPlugin in src/Pyz/Yves/Router/RouterDependencyProvider.php (see README step 4). Missing: %s.',
                    implode(', ', $missingRouteNames),
                ),
        ];
    }

    /**
     * The check-installation route itself is deliberately excluded: reaching this action already proves
     * it is registered, so re-checking it here would only ever report success.
     *
     * @return array<string>
     */
    protected function getWidgetRouteNames(): array
    {
        return [
            SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_TOKEN_SOURCE,
            SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYSIS_PATH,
            SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_COMPONENT_CONFIG,
        ];
    }

    /**
     * @param string $routeName
     *
     * @return bool
     */
    protected function isRouteRegistered(string $routeName): bool
    {
        try {
            $this->getRouter()->generate($routeName);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        }
    }

    /**
     * Pure over an already-resolved dispatcher and event name — no framework bootstrap needed to test
     * this in isolation, unlike the container access in {@see checkEventListener()}.
     *
     * A registered plugin's listener closure is created INSIDE `extend()`, an instance method on the
     * plugin — PHP auto-binds `$this` on any closure created inside a non-static method, so the closure's
     * bound object is the plugin instance itself (see `SearchDebugContextEventDispatcherPlugin::extend()`).
     * Reflecting that binding is the only way to identify WHICH plugin registered a given listener, since
     * Symfony's `EventDispatcherInterface::getListeners()` returns plain callables with no origin info.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param string $eventName
     * @param class-string $listenerClassName
     *
     * @return bool
     */
    protected function isListenerBound(EventDispatcherInterface $eventDispatcher, string $eventName, string $listenerClassName): bool
    {
        foreach ($eventDispatcher->getListeners($eventName) as $listener) {
            if (!($listener instanceof Closure)) {
                continue;
            }

            $boundObject = (new ReflectionFunction($listener))->getClosureThis();

            if ($boundObject instanceof $listenerClassName) {
                return true;
            }
        }

        return false;
    }
}
