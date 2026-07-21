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
use SprykerCommunity\Yves\SearchDebug\Plugin\EventDispatcher\SearchDebugContextEventDispatcherPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\CheckInstallationController;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * `indexAction()` itself needs a real Silex/Symfony container (`$this->can()`, `$this->getTwig()`,
 * `$this->getApplication()`, `$this->getRouter()`) and is left to integration coverage, mirroring
 * `TokenSourceControllerTest` — but `isListenerBound()` only takes an already-resolved
 * `EventDispatcherInterface`, so it is exercised directly here via reflection, `new`-instantiating the
 * controller (`AbstractController` has no required constructor arguments).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group CheckInstallationControllerTest
 * Add your own group annotations below this line
 */
class CheckInstallationControllerTest extends Unit
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
     *
     * @return void
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
     *
     * @return bool
     */
    protected function invokeIsListenerBound(EventDispatcher $eventDispatcher, string $eventName, string $listenerClassName): bool
    {
        $reflectionMethod = new ReflectionMethod(CheckInstallationController::class, 'isListenerBound');
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke(new CheckInstallationController(), $eventDispatcher, $eventName, $listenerClassName);
    }
}
