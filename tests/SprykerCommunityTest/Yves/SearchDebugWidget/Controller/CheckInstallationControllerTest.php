<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Closure;
use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\EventDispatcher\EventDispatcher;
use Spryker\Yves\Kernel\View\View;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin;
use SprykerCommunity\Yves\SearchDebug\Plugin\Twig\SearchDebugTwigPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\CheckInstallationController;
use SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router\SearchDebugWidgetRouteProviderPlugin;
use Symfony\Cmf\Component\Routing\ChainRouterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * `indexAction()` and its container-touching helpers (`checkTwigFunction()`, `checkEventListener()`,
 * `checkRoutes()`, `isTwigFunctionCallable()`, `isRouteRegistered()`) are exercised here against a
 * minimal hand-built `ContainerInterface` fixture (real `Twig\Environment`/`EventDispatcher` instances, a
 * mocked router) rather than a full Silex/Symfony application boot — `AbstractController` only ever
 * reaches its container through `getApplication()->get($id)`, so a fixture answering exactly the 3
 * service ids this controller asks for (`twig`, `dispatcher`, `routers`) is a faithful stand-in without
 * needing the real app. `can()`/`runChecks()`/`view()` are partial-mocked where `indexAction()`'s own
 * branching (not a sub-check's internals) is what's under test.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group CheckInstallationControllerTest
 * Add your own group annotations below this line
 * @group Portable
 */
class CheckInstallationControllerTest extends Unit
{
    public function testIndexActionReturnsAForbiddenResponseWhenThePermissionIsMissing(): void
    {
        // Arrange
        $controller = $this->getMockBuilder(CheckInstallationController::class)
            ->onlyMethods(['can', 'renderView'])
            ->getMock();
        $controller->method('can')->with(SeeSearchDebugInfoPermissionPlugin::KEY)->willReturn(false);
        $controller->expects($this->once())
            ->method('renderView')
            ->with(
                '@SearchDebugWidget/views/check-installation/permission-denied.twig',
                [],
                $this->callback(fn (Response $response): bool => $response->getStatusCode() === Response::HTTP_FORBIDDEN),
            )
            ->willReturn(new Response('', Response::HTTP_FORBIDDEN));

        // Act
        $result = $controller->indexAction();

        // Assert
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(Response::HTTP_FORBIDDEN, $result->getStatusCode());
    }

    public function testIndexActionReturnsTheViewWithChecksWhenPermitted(): void
    {
        // Arrange
        $checks = [['label' => 'a check', 'passed' => true, 'remedy' => null]];
        $controller = $this->getMockBuilder(CheckInstallationController::class)
            ->onlyMethods(['can', 'runChecks'])
            ->getMock();
        $controller->method('can')->with(SeeSearchDebugInfoPermissionPlugin::KEY)->willReturn(true);
        $controller->method('runChecks')->willReturn($checks);

        // Act — view() itself is pure (inherited from AbstractController, just `new View(...)`), so it
        // runs for real here rather than being mocked too.
        $result = $controller->indexAction();

        // Assert
        $this->assertInstanceOf(View::class, $result);
        $this->assertSame(['checks' => $checks], $result->getData());
        $this->assertSame('@SearchDebugWidget/views/check-installation/check-installation.twig', $result->getTemplate());
    }

    public function testRunChecksReturnsAllThreeChecksInOrder(): void
    {
        // Arrange — a container that passes every individual check, so the only thing under test here is
        // that all three run and land in the right order.
        $twig = new Environment(new ArrayLoader());
        $twig->addFunction(new TwigFunction(SearchDebugTwigPlugin::FUNCTION_NAME_TOKEN_COLORS, fn () => []));
        $dispatcher = new EventDispatcher();
        (new SearchDebugContextEventDispatcherPlugin())->extend($dispatcher, $this->createMock(ContainerInterface::class));
        $router = $this->createMock(ChainRouterInterface::class);
        $router->method('generate')->willReturn('/some/url');

        // Act
        $checks = $this->invokeProtectedMethod('runChecks', [], $this->createContainer($twig, $dispatcher, $router));

        // Assert
        $this->assertCount(3, $checks);
        $this->assertTrue($checks[0]['passed']);
        $this->assertTrue($checks[1]['passed']);
        $this->assertTrue($checks[2]['passed']);
    }

    public function testCheckTwigFunctionReturnsPassedWhenTheFunctionIsRegistered(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader());
        $twig->addFunction(new TwigFunction(SearchDebugTwigPlugin::FUNCTION_NAME_TOKEN_COLORS, fn () => []));

        // Act
        $check = $this->invokeProtectedMethod('checkTwigFunction', [], $this->createContainer($twig));

        // Assert
        $this->assertTrue($check['passed']);
        $this->assertNull($check['remedy']);
    }

    /**
     * `debug: true` is NOT about debugging this test — it changes `Environment::$optionsHash`, which
     * feeds directly into the generated template class name (see `Environment::getTemplateClass()`).
     * Without it, this environment would compile the EXACT same source string
     * (`{{ searchDebugTokenColors([]) }}`) to the SAME class name as the "is registered" test above,
     * registering ONE PHP class for both. Since `checkTwigFunction()` always probes the one hardcoded
     * function name, whichever of these two tests runs first "wins" the class name process-wide — without
     * this, the second test to run would silently reuse the FIRST test's already-compiled (successful)
     * class via `class_exists()`, never re-validating that the function is actually missing here.
     */
    public function testCheckTwigFunctionReturnsFailedWithARemedyWhenTheFunctionIsNotRegistered(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader(), ['debug' => true]);

        // Act
        $check = $this->invokeProtectedMethod('checkTwigFunction', [], $this->createContainer($twig));

        // Assert
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('TwigDependencyProvider.php', $check['remedy']);
    }

    public function testIsTwigFunctionCallableReturnsTrueWhenTheFunctionCompiles(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader());
        $twig->addFunction(new TwigFunction('someFunction', fn () => []));

        // Act
        $isCallable = $this->invokeProtectedMethod('isTwigFunctionCallable', ['someFunction'], $this->createContainer($twig));

        // Assert
        $this->assertTrue($isCallable);
    }

    public function testIsTwigFunctionCallableReturnsFalseWhenTheFunctionDoesNotExist(): void
    {
        // Arrange
        $twig = new Environment(new ArrayLoader());

        // Act
        $isCallable = $this->invokeProtectedMethod('isTwigFunctionCallable', ['notRegistered'], $this->createContainer($twig));

        // Assert
        $this->assertFalse($isCallable);
    }

    public function testCheckEventListenerReturnsPassedWhenTheListenerIsRegistered(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();
        (new SearchDebugContextEventDispatcherPlugin())->extend($dispatcher, $this->createMock(ContainerInterface::class));

        // Act
        $check = $this->invokeProtectedMethod('checkEventListener', [], $this->createContainer(null, $dispatcher));

        // Assert
        $this->assertTrue($check['passed']);
        $this->assertNull($check['remedy']);
    }

    public function testCheckEventListenerReturnsFailedWithARemedyWhenTheListenerIsNotRegistered(): void
    {
        // Arrange
        $dispatcher = new EventDispatcher();

        // Act
        $check = $this->invokeProtectedMethod('checkEventListener', [], $this->createContainer(null, $dispatcher));

        // Assert
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString('EventDispatcherDependencyProvider.php', $check['remedy']);
    }

    public function testCheckRoutesReturnsPassedWhenAllRoutesAreRegistered(): void
    {
        // Arrange
        $router = $this->createMock(ChainRouterInterface::class);
        $router->method('generate')->willReturn('/some/url');

        // Act
        $check = $this->invokeProtectedMethod('checkRoutes', [], $this->createContainer(null, null, $router));

        // Assert
        $this->assertTrue($check['passed']);
        $this->assertNull($check['remedy']);
    }

    public function testCheckRoutesReturnsFailedListingEveryMissingRouteWhenSomeAreMissing(): void
    {
        // Arrange
        $router = $this->createMock(ChainRouterInterface::class);
        $router->method('generate')->willReturnCallback(function (string $routeName): string {
            if ($routeName === SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYSIS_PATH) {
                throw new RouteNotFoundException();
            }

            return '/some/url';
        });

        // Act
        $check = $this->invokeProtectedMethod('checkRoutes', [], $this->createContainer(null, null, $router));

        // Assert
        $this->assertFalse($check['passed']);
        $this->assertStringContainsString(SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYSIS_PATH, $check['remedy']);
    }

    public function testGetWidgetRouteNamesReturnsTheFiveNonCheckInstallationRoutes(): void
    {
        // Act
        $routeNames = $this->invokeProtectedMethod('getWidgetRouteNames', []);

        // Assert
        $this->assertSame(
            [
                SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_TOKEN_SOURCE,
                SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYSIS_PATH,
                SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_COMPONENT_CONFIG,
                SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYZE,
                SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_SKU_LOOKUP,
            ],
            $routeNames,
        );
    }

    public function testIsRouteRegisteredReturnsTrueWhenTheRouterGeneratesAUrl(): void
    {
        // Arrange
        $router = $this->createMock(ChainRouterInterface::class);
        $router->method('generate')->willReturn('/some/url');

        // Act
        $isRegistered = $this->invokeProtectedMethod('isRouteRegistered', ['some-route'], $this->createContainer(null, null, $router));

        // Assert
        $this->assertTrue($isRegistered);
    }

    public function testIsRouteRegisteredReturnsFalseWhenTheRouterThrowsRouteNotFoundException(): void
    {
        // Arrange
        $router = $this->createMock(ChainRouterInterface::class);
        $router->method('generate')->willThrowException(new RouteNotFoundException());

        // Act
        $isRegistered = $this->invokeProtectedMethod('isRouteRegistered', ['some-route'], $this->createContainer(null, null, $router));

        // Assert
        $this->assertFalse($isRegistered);
    }

    public function testIsListenerBoundReturnsTrueWhenThePluginRegisteredAListenerForTheEvent(): void
    {
        // Arrange
        $eventDispatcher = new EventDispatcher();
        $plugin = new SearchDebugContextEventDispatcherPlugin();
        $plugin->extend($eventDispatcher, $this->createMock(ContainerInterface::class));

        // Act
        $isBound = $this->invokeIsListenerBound($eventDispatcher, KernelEvents::REQUEST, SearchDebugContextEventDispatcherPlugin::class);

        // Assert
        $this->assertTrue($isBound);
    }

    public function testIsListenerBoundReturnsFalseWhenNoListenerIsRegisteredForTheEvent(): void
    {
        // Arrange
        $eventDispatcher = new EventDispatcher();

        // Act
        $isBound = $this->invokeIsListenerBound($eventDispatcher, KernelEvents::REQUEST, SearchDebugContextEventDispatcherPlugin::class);

        // Assert
        $this->assertFalse($isBound);
    }

    /**
     * A listener IS registered for the event, but bound to an unrelated object — confirms the check
     * identifies the specific plugin by its closure's bound `$this`, not merely "something listens".
     */
    public function testIsListenerBoundReturnsFalseWhenTheRegisteredListenerBelongsToADifferentClass(): void
    {
        // Arrange
        $eventDispatcher = new EventDispatcher();
        $unrelatedListenerOwner = new class {
        };
        $listener = Closure::bind(function (): void {
        }, $unrelatedListenerOwner);
        $eventDispatcher->addListener(KernelEvents::REQUEST, $listener);

        // Act
        $isBound = $this->invokeIsListenerBound($eventDispatcher, KernelEvents::REQUEST, SearchDebugContextEventDispatcherPlugin::class);

        // Assert
        $this->assertFalse($isBound);
    }

    /**
     * @param \Spryker\Shared\EventDispatcher\EventDispatcher $eventDispatcher
     * @param string $eventName
     * @param class-string $listenerClassName
     */
    protected function invokeIsListenerBound(EventDispatcher $eventDispatcher, string $eventName, string $listenerClassName): bool
    {
        $reflectionMethod = new ReflectionMethod(CheckInstallationController::class, 'isListenerBound');

        return $reflectionMethod->invoke(new CheckInstallationController(), $eventDispatcher, $eventName, $listenerClassName);
    }

    /**
     * @param string $methodName
     * @param array<mixed> $args
     * @param \Spryker\Service\Container\ContainerInterface|null $container
     *
     * @return mixed
     */
    protected function invokeProtectedMethod(string $methodName, array $args, ?ContainerInterface $container = null)
    {
        $controller = new CheckInstallationController();

        if ($container !== null) {
            $controller->setApplication($container);
        }

        $reflectionMethod = new ReflectionMethod(CheckInstallationController::class, $methodName);

        return $reflectionMethod->invoke($controller, ...$args);
    }

    /**
     * A minimal `ContainerInterface` fixture answering exactly the 3 service ids `AbstractController`'s
     * `getTwig()`/`getApplication()->get('dispatcher')`/`getRouter()` ask for — every other
     * `ContainerInterface` method is unused by this controller and stubbed as a no-op.
     *
     * @param \Twig\Environment|null $twig
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface|null $dispatcher
     * @param \Symfony\Cmf\Component\Routing\ChainRouterInterface|null $router
     */
    protected function createContainer(
        ?Environment $twig = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?ChainRouterInterface $router = null,
    ): ContainerInterface {
        return new class ($twig, $dispatcher, $router) implements ContainerInterface {
            public function __construct(
                protected ?Environment $twig,
                protected ?EventDispatcherInterface $dispatcher,
                protected ?ChainRouterInterface $router,
            ) {
            }

            /**
             * @param string $id
             *
             * @return mixed
             */
            public function get(string $id)
            {
                return match ($id) {
                    'twig' => $this->twig,
                    'dispatcher' => $this->dispatcher,
                    'routers' => $this->router,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return $this->get($id) !== null;
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $id
             * @param mixed $service
             */
            public function set(string $id, $service): void
            {
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $id
             * @param mixed $service
             */
            public function setGlobal(string $id, $service): void
            {
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $id
             * @param array<string, mixed> $configuration
             */
            public function configure(string $id, array $configuration): void
            {
            }

            /**
             * $service is deliberately untyped, matching `ContainerInterface::extend()`'s own untyped
             * parameter — narrowing it to `Closure` here would violate parameter contravariance against
             * the interface. The return type has no such constraint (covariant), so it stays native.
             *
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $id
             * @param mixed $service
             */
            public function extend(string $id, $service): Closure
            {
                return $service;
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
             *
             * @param string $id
             */
            public function remove(string $id): void
            {
            }

            /**
             * @param \Closure|object $service
             *
             * @return \Closure|object
             */
            public function protect($service)
            {
                return $service;
            }

            /**
             * @param \Closure|object $service
             *
             * @return \Closure|object
             */
            public function factory($service)
            {
                return $service;
            }
        };
    }
}
