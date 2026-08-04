<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\TokenSourceController;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `indexAction()`'s own branching (permission gate, required-param validation, not-found handling) is
 * exercised here via a partial mock of the controller itself, overriding exactly the 3 protected methods
 * that reach the framework (`can()`, `getFactory()`, `getLocale()`) — `view()` is left real since it is
 * pure (inherited from `AbstractController`, just `new View(...)`). `sanitizeFieldBoosts()` has no
 * framework dependency at all, so it is exercised directly via reflection, `new`-instantiating the
 * controller (`AbstractController` has no required constructor arguments).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group TokenSourceControllerTest
 * Add your own group annotations below this line
 */
class TokenSourceControllerTest extends Unit
{
    public function testIndexActionThrowsAccessDeniedWhenThePermissionIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(false);

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $controller->indexAction(new Request(['sku' => 'sku-1', 'token' => 'cable']));
    }

    public function testIndexActionThrowsBadRequestWhenTheSkuIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(true);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act
        $controller->indexAction(new Request(['token' => 'cable']));
    }

    public function testIndexActionThrowsBadRequestWhenTheTokenIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(true);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act
        $controller->indexAction(new Request(['sku' => 'sku-1']));
    }

    public function testIndexActionThrowsNotFoundWhenTheResolverFindsNoMatchingProduct(): void
    {
        // Arrange
        $resolverMock = $this->createMock(TokenSourceResolverInterface::class);
        $resolverMock->method('resolve')->willReturn(null);
        $controller = $this->createController(true, $resolverMock);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->indexAction(new Request(['sku' => 'sku-1', 'token' => 'cable']));
    }

    public function testIndexActionReturnsTheViewBuiltFromTheResolverResultWhenPermitted(): void
    {
        // Arrange
        $resolverResult = ['productTitle' => 'Cable', 'productSku' => 'sku-1', 'tiers' => []];
        $resolverMock = $this->createMock(TokenSourceResolverInterface::class);
        $resolverMock
            ->method('resolve')
            ->with('sku-1', 'cable', 'en_US', ['full-text' => 5])
            ->willReturn($resolverResult);
        $controller = $this->createController(true, $resolverMock);

        // Act — the token is uppercase in the URL, boosts nested under the `boosts` query key: both
        // exercise real behavior of `indexAction()` itself, not just the resolver's own mocked return.
        $result = $controller->indexAction(new Request(['sku' => 'sku-1', 'token' => 'CABLE', 'boosts' => ['full-text' => '5']]));

        // Assert
        $this->assertSame(
            ['productTitle' => 'Cable', 'productSku' => 'sku-1', 'token' => 'cable', 'tiers' => []],
            $result->getData(),
        );
        $this->assertSame('@SearchDebugWidget/views/token-source/token-source.twig', $result->getTemplate());
    }

    protected function createController(bool $isPermitted, ?TokenSourceResolverInterface $resolver = null): TokenSourceController
    {
        $factoryMock = $this->getMockBuilder(SearchDebugWidgetFactory::class)
            ->onlyMethods(['createTokenSourceResolver'])
            ->getMock();
        $factoryMock->method('createTokenSourceResolver')->willReturn($resolver ?? $this->createMock(TokenSourceResolverInterface::class));

        $controller = $this->getMockBuilder(TokenSourceController::class)
            ->onlyMethods(['can', 'getFactory', 'getLocale'])
            ->getMock();
        $controller->method('can')->with(SeeSearchDebugInfoPermissionPlugin::KEY)->willReturn($isPermitted);
        $controller->method('getFactory')->willReturn($factoryMock);
        $controller->method('getLocale')->willReturn('en_US');

        return $controller;
    }

    public function testSanitizeFieldBoostsKeepsWellFormedFieldNameToIntegerBoostPairs(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => '1', 'full-text-boosted' => 5];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 1, 'full-text-boosted' => 5], $fieldBoosts);
    }

    /**
     * A numeric query-string key (e.g. `boosts[0]=5`) parses to an int array key in PHP, not a string one
     * — such an entry is dropped rather than coerced, since a boost is only ever meaningful keyed by a
     * real field name.
     */
    public function testSanitizeFieldBoostsDropsEntriesWithANonStringFieldName(): void
    {
        // Arrange
        $rawFieldBoosts = [0 => '5', 'full-text' => '3'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 3], $fieldBoosts);
    }

    /**
     * A malformed/hand-edited `boosts[field][]=x` query string produces a nested array value where a
     * scalar boost is expected — dropped defensively rather than letting the `(int)` cast throw or coerce
     * an array into a nonsensical number.
     */
    public function testSanitizeFieldBoostsDropsEntriesWhoseBoostValueIsAnArray(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => ['nested', 'array'], 'full-text-boosted' => '5'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text-boosted' => 5], $fieldBoosts);
    }

    public function testSanitizeFieldBoostsReturnsAnEmptyArrayForAnEmptyInput(): void
    {
        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts([]);

        // Assert
        $this->assertSame([], $fieldBoosts);
    }

    /**
     * A non-numeric boost string casts to `0` via PHP's `(int)` cast rather than being dropped — the
     * method only guards against structurally wrong shapes (non-string keys, array values), not against
     * nonsensical-but-scalar boost values.
     */
    public function testSanitizeFieldBoostsCastsANonNumericBoostStringToZero(): void
    {
        // Arrange
        $rawFieldBoosts = ['full-text' => 'not-a-number'];

        // Act
        $fieldBoosts = $this->invokeSanitizeFieldBoosts($rawFieldBoosts);

        // Assert
        $this->assertSame(['full-text' => 0], $fieldBoosts);
    }

    /**
     * @param array<mixed> $rawFieldBoosts
     *
     * @return array<string, int>
     */
    protected function invokeSanitizeFieldBoosts(array $rawFieldBoosts): array
    {
        $reflectionMethod = new ReflectionMethod(TokenSourceController::class, 'sanitizeFieldBoosts');

        return $reflectionMethod->invoke(new TokenSourceController(), $rawFieldBoosts);
    }
}
