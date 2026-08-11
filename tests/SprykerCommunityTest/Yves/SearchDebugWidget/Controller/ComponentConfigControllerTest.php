<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\ComponentConfigController;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `indexAction()`'s branching (permission gate, `type`/`name` param validation, not-found handling, the
 * formatted-view assembly) is exercised via a partial mock of the controller itself, overriding exactly
 * the 2 protected methods that reach the framework (`can()`, `getFactory()`) — same approach
 * {@see \SprykerCommunityTest\Yves\SearchDebugWidget\Controller\AnalysisPathControllerTest} uses.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group ComponentConfigControllerTest
 * Add your own group annotations below this line
 */
class ComponentConfigControllerTest extends Unit
{
    public function testIndexActionThrowsAccessDeniedWhenThePermissionIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(false);

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $controller->indexAction(new Request(['type' => 'filter', 'name' => 'fulltext_synonyms']));
    }

    public function testIndexActionThrowsBadRequestWhenTheTypeIsNotOneOfTheAllowedKinds(): void
    {
        // Arrange
        $controller = $this->createController(true);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act
        $controller->indexAction(new Request(['type' => 'analyzer', 'name' => 'fulltext_synonyms']));
    }

    public function testIndexActionThrowsBadRequestWhenTheTypeIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(true);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act
        $controller->indexAction(new Request(['name' => 'fulltext_synonyms']));
    }

    public function testIndexActionThrowsBadRequestWhenTheNameIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(true);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act
        $controller->indexAction(new Request(['type' => 'filter']));
    }

    public function testIndexActionThrowsNotFoundWhenNoComponentMatches(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getComponentConfig')->willReturn(null);
        $controller = $this->createController(true, $searchDebugClientMock);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->indexAction(new Request(['type' => 'filter', 'name' => 'unknown_filter']));
    }

    public function testIndexActionRendersTheFormattedComponentOnSuccess(): void
    {
        // Arrange
        $rawComponent = [
            'name' => 'fulltext_synonyms',
            'type' => 'synonym',
            'config' => ['synonyms' => ['handcart, trolley']],
        ];
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock
            ->expects($this->once())
            ->method('getComponentConfig')
            ->with('filter', 'fulltext_synonyms')
            ->willReturn($rawComponent);
        $formatterMock = $this->createMock(ComponentConfigFormatterInterface::class);
        $formatterMock
            ->expects($this->once())
            ->method('format')
            ->with($rawComponent['config'])
            ->willReturn(['synonyms' => ['handcart, trolley']]);
        $controller = $this->createController(true, $searchDebugClientMock, $formatterMock);

        // Act
        $result = $controller->indexAction(new Request(['type' => 'filter', 'name' => 'fulltext_synonyms']));

        // Assert
        $this->assertSame('fulltext_synonyms', $result->getData()['name']);
        $this->assertSame('synonym', $result->getData()['type']);
        $this->assertSame(['synonyms' => ['handcart, trolley']], $result->getData()['config']);
        $this->assertSame('@SearchDebugWidget/views/component-config/component-config.twig', $result->getTemplate());
    }

    protected function createController(
        bool $isPermitted,
        ?SearchDebugClientInterface $searchDebugClient = null,
        ?ComponentConfigFormatterInterface $formatter = null,
    ): ComponentConfigController {
        $factoryMock = $this->getMockBuilder(SearchDebugWidgetFactory::class)
            ->onlyMethods(['getSearchDebugClient', 'createComponentConfigFormatter'])
            ->getMock();
        $factoryMock->method('getSearchDebugClient')->willReturn($searchDebugClient ?? $this->createMock(SearchDebugClientInterface::class));
        $factoryMock->method('createComponentConfigFormatter')->willReturn($formatter ?? $this->createMock(ComponentConfigFormatterInterface::class));

        $controller = $this->getMockBuilder(ComponentConfigController::class)
            ->onlyMethods(['can', 'getFactory'])
            ->getMock();
        $controller->method('can')->with(SeeSearchDebugInfoPermissionPlugin::KEY)->willReturn($isPermitted);
        $controller->method('getFactory')->willReturn($factoryMock);

        return $controller;
    }
}
