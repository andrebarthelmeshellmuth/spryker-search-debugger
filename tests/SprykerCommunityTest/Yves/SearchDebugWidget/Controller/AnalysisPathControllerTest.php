<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\AnalysisPathController;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolverInterface;
use SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `indexAction()`'s own branching (permission gate, required-param validation, the explicit-offset-vs-
 * first-match-fallback choice, not-found handling) is exercised here via a partial mock of the controller
 * itself, overriding exactly the 2 protected methods that reach the framework (`can()`, `getFactory()`) —
 * `view()` is left real since it is pure (inherited from `AbstractController`, just `new View(...)`). The
 * three helpers below (`assignStepColors`, `resolveUseSearchAnalyzer`, `resolveExplicitOffset`,
 * `findFirstMatchOffset`) have no framework dependency at all, so they are exercised directly via
 * reflection, `new`-instantiating the controller (`AbstractController` has no required constructor
 * arguments).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group AnalysisPathControllerTest
 * Add your own group annotations below this line
 * @group Portable
 */
class AnalysisPathControllerTest extends Unit
{
    public function testIndexActionThrowsAccessDeniedWhenThePermissionIsMissing(): void
    {
        // Arrange
        $controller = $this->createController(false);

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $controller->indexAction(new Request(['text' => 'cable', 'token' => 'cable']));
    }

    public function testIndexActionThrowsBadRequestWhenTheTextIsMissing(): void
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
        $controller->indexAction(new Request(['text' => 'cable']));
    }

    /**
     * No explicit offset in the URL AND the token isn't found anywhere in the text via the
     * first-match fallback — the `getTextTokenOffsets()` round trip never even reaches the path resolver.
     */
    public function testIndexActionThrowsNotFoundWhenNoOffsetCanBeResolvedAtAll(): void
    {
        // Arrange
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('getTextTokenOffsets')->willReturn([]);
        $controller = $this->createController(true, null, $searchDebugClientMock);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->indexAction(new Request(['text' => 'a cable', 'token' => 'cable']));
    }

    public function testIndexActionThrowsNotFoundWhenThePathResolverCannotReconstructAPath(): void
    {
        // Arrange
        $pathResolverMock = $this->createMock(AnalysisPathResolverInterface::class);
        $pathResolverMock->method('resolve')->willReturn(null);
        $controller = $this->createController(true, $pathResolverMock);

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $controller->indexAction(new Request(['text' => 'cable', 'token' => 'cable', 'startOffset' => '0', 'endOffset' => '5']));
    }

    public function testIndexActionUsesTheExplicitOffsetWhenBothOffsetParametersArePresent(): void
    {
        // Arrange
        $path = [['text' => 'cable', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null]];
        $pathResolverMock = $this->createMock(AnalysisPathResolverInterface::class);
        $pathResolverMock->expects($this->once())->method('resolve')->with('a cable cable', 'cable', 8, 13, false)->willReturn($path);
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        // The client must never be asked to resolve offsets at all — an explicit offset skips that
        // round trip entirely, it does not merely take precedence over its result.
        $searchDebugClientMock->expects($this->never())->method('getTextTokenOffsets');
        $controller = $this->createController(true, $pathResolverMock, $searchDebugClientMock);

        // Act — two occurrences of "cable" in the text; the explicit offset (8, 13) picks the SECOND one.
        $result = $controller->indexAction(new Request(['text' => 'a cable cable', 'token' => 'cable', 'startOffset' => '8', 'endOffset' => '13']));

        // Assert
        $this->assertSame('a cable cable', $result->getData()['text']);
        $this->assertSame('cable', $result->getData()['token']);
        $this->assertArrayHasKey('colorClass', $result->getData()['path'][0]);
    }

    public function testIndexActionFallsBackToTheFirstMatchOffsetWhenNoExplicitOffsetIsGiven(): void
    {
        // Arrange
        $path = [['text' => 'cable', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null]];
        $pathResolverMock = $this->createMock(AnalysisPathResolverInterface::class);
        $pathResolverMock->expects($this->once())->method('resolve')->with('a cable', 'cable', 2, 7, false)->willReturn($path);
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock
            ->method('getTextTokenOffsets')
            ->with('a cable', false)
            ->willReturn([['token' => 'cable', 'startOffset' => 2, 'endOffset' => 7]]);
        $controller = $this->createController(true, $pathResolverMock, $searchDebugClientMock);

        // Act
        $result = $controller->indexAction(new Request(['text' => 'a cable', 'token' => 'cable']));

        // Assert
        $this->assertSame(['text', 'token', 'path'], array_keys($result->getData()));
        $this->assertSame('@SearchDebugWidget/views/token-analysis/token-analysis.twig', $result->getTemplate());
    }

    public function testIndexActionForwardsTheSearchAnalyzerFlagWhenTracingAQueryToken(): void
    {
        // Arrange
        $pathResolverMock = $this->createMock(AnalysisPathResolverInterface::class);
        $pathResolverMock->expects($this->once())->method('resolve')->with('cable', 'cable', 0, 5, true)->willReturn([]);
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->expects($this->once())->method('getTextTokenOffsets')->with('cable', true)->willReturn([['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]]);
        $controller = $this->createController(true, $pathResolverMock, $searchDebugClientMock);

        // Act
        $controller->indexAction(new Request(['text' => 'cable', 'token' => 'cable', 'analyzer' => 'search']));
    }

    protected function createController(
        bool $isPermitted,
        ?AnalysisPathResolverInterface $pathResolver = null,
        ?SearchDebugClientInterface $searchDebugClient = null,
    ): AnalysisPathController {
        $factoryMock = $this->getMockBuilder(SearchDebugWidgetFactory::class)
            ->onlyMethods(['createAnalysisPathResolver', 'getSearchDebugClient'])
            ->getMock();
        $factoryMock->method('createAnalysisPathResolver')->willReturn($pathResolver ?? $this->createMock(AnalysisPathResolverInterface::class));
        $factoryMock->method('getSearchDebugClient')->willReturn($searchDebugClient ?? $this->createMock(SearchDebugClientInterface::class));

        $controller = $this->getMockBuilder(AnalysisPathController::class)
            ->onlyMethods(['can', 'getFactory'])
            ->getMock();
        $controller->method('can')->with(SeeSearchDebugInfoPermissionPlugin::KEY)->willReturn($isPermitted);
        $controller->method('getFactory')->willReturn($factoryMock);

        return $controller;
    }

    public function testAssignStepColorsGivesTheFirstStepTheFirstPaletteColor(): void
    {
        // Arrange
        $path = [
            ['text' => 'raw text', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
        ];

        // Act
        $coloredPath = $this->invokeAssignStepColors($path);

        // Assert
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 1), $coloredPath[0]['colorClass']);
    }

    public function testAssignStepColorsReusesTheSameColorForByteIdenticalText(): void
    {
        // Arrange — a filter that passes the text through unchanged (e.g. lowercase on an
        // already-lowercase string) must not be mistaken for "a new string".
        $path = [
            ['text' => 'cable', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ['text' => 'cable', 'operation' => 'lowercase', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
        ];

        // Act
        $coloredPath = $this->invokeAssignStepColors($path);

        // Assert
        $this->assertSame($coloredPath[0]['colorClass'], $coloredPath[1]['colorClass']);
    }

    public function testAssignStepColorsGivesADifferentTextADifferentColor(): void
    {
        // Arrange
        $path = [
            ['text' => 'Eisenhammer', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ['text' => 'hammer', 'operation' => 'synonym', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
        ];

        // Act
        $coloredPath = $this->invokeAssignStepColors($path);

        // Assert
        $this->assertNotSame($coloredPath[0]['colorClass'], $coloredPath[1]['colorClass']);
    }

    public function testAssignStepColorsReusesAnEarlierColorWhenTheTextReappearsLaterInThePath(): void
    {
        // Arrange — e.g. a filter chain that ends up back at an earlier intermediate string.
        $path = [
            ['text' => 'a', 'operation' => null, 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ['text' => 'b', 'operation' => 'op', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
            ['text' => 'a', 'operation' => 'op', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null],
        ];

        // Act
        $coloredPath = $this->invokeAssignStepColors($path);

        // Assert
        $this->assertSame($coloredPath[0]['colorClass'], $coloredPath[2]['colorClass']);
        $this->assertNotSame($coloredPath[0]['colorClass'], $coloredPath[1]['colorClass']);
    }

    public function testAssignStepColorsWrapsAroundPastThePaletteSize(): void
    {
        // Arrange — one more distinct string than the palette has colors for.
        $path = [];
        for ($index = 0; $index < SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT + 1; $index++) {
            $path[] = ['text' => 'text-' . $index, 'operation' => 'op', 'definition' => null, 'componentKind' => null, 'componentName' => null, 'definitionTruncated' => false, 'highlightedHtml' => null];
        }

        // Act
        $coloredPath = $this->invokeAssignStepColors($path);

        // Assert — the (COUNT + 1)th distinct string wraps back around to the first color.
        $this->assertSame($coloredPath[0]['colorClass'], $coloredPath[SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT]['colorClass']);
    }

    public function testResolveUseSearchAnalyzerReturnsFalseWhenTheAnalyzerParameterIsAbsent(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertFalse($useSearchAnalyzer);
    }

    public function testResolveUseSearchAnalyzerReturnsFalseForAnyUnrecognizedValue(): void
    {
        // Arrange
        $request = new Request(['analyzer' => 'index']);

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertFalse($useSearchAnalyzer);
    }

    public function testResolveUseSearchAnalyzerReturnsTrueForTheRecognizedSearchValue(): void
    {
        // Arrange
        $request = new Request(['analyzer' => 'search']);

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertTrue($useSearchAnalyzer);
    }

    public function testResolveExplicitOffsetReturnsNullWhenBothOffsetParametersAreMissing(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    public function testResolveExplicitOffsetReturnsNullWhenOnlyOneOffsetParameterIsPresent(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '0']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    public function testResolveExplicitOffsetReturnsNullWhenTheStartOffsetIsNegative(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '-1', 'endOffset' => '5']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    public function testResolveExplicitOffsetReturnsNullWhenTheEndOffsetDoesNotExceedTheStartOffset(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '5', 'endOffset' => '5']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    public function testResolveExplicitOffsetReturnsTheOffsetsWhenBothAreValid(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '4', 'endOffset' => '9']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertSame(['startOffset' => 4, 'endOffset' => 9], $offset);
    }

    public function testFindFirstMatchOffsetReturnsTheFirstMatchingTokenOffset(): void
    {
        // Arrange
        $tokenOffsets = [
            ['token' => 'haus', 'startOffset' => 0, 'endOffset' => 4],
            ['token' => 'tuere', 'startOffset' => 4, 'endOffset' => 9],
            ['token' => 'tuere', 'startOffset' => 15, 'endOffset' => 20],
        ];

        // Act
        $offset = $this->invokeFindFirstMatchOffset($tokenOffsets, 'tuere');

        // Assert — the FIRST occurrence, not the second one further in the array.
        $this->assertSame(['startOffset' => 4, 'endOffset' => 9], $offset);
    }

    public function testFindFirstMatchOffsetReturnsNullWhenTheTokenIsNotPresent(): void
    {
        // Arrange
        $tokenOffsets = [
            ['token' => 'haus', 'startOffset' => 0, 'endOffset' => 4],
        ];

        // Act
        $offset = $this->invokeFindFirstMatchOffset($tokenOffsets, 'unrelated');

        // Assert
        $this->assertNull($offset);
    }

    public function testFindFirstMatchOffsetReturnsNullForAnEmptyTokenOffsetList(): void
    {
        // Act
        $offset = $this->invokeFindFirstMatchOffset([], 'anything');

        // Assert
        $this->assertNull($offset);
    }

    /**
     * @param array<int, array<string, mixed>> $path
     *
     * @return array<int, array<string, mixed>>
     */
    protected function invokeAssignStepColors(array $path): array
    {
        $reflectionMethod = new ReflectionMethod(AnalysisPathController::class, 'assignStepColors');

        return $reflectionMethod->invoke(new AnalysisPathController(), $path);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function invokeResolveUseSearchAnalyzer(Request $request): bool
    {
        $reflectionMethod = new ReflectionMethod(AnalysisPathController::class, 'resolveUseSearchAnalyzer');

        return $reflectionMethod->invoke(new AnalysisPathController(), $request);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array{startOffset: int, endOffset: int}|null
     */
    protected function invokeResolveExplicitOffset(Request $request): ?array
    {
        $reflectionMethod = new ReflectionMethod(AnalysisPathController::class, 'resolveExplicitOffset');

        return $reflectionMethod->invoke(new AnalysisPathController(), $request);
    }

    /**
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokenOffsets
     * @param string $token
     *
     * @return array{startOffset: int, endOffset: int}|null
     */
    protected function invokeFindFirstMatchOffset(array $tokenOffsets, string $token): ?array
    {
        $reflectionMethod = new ReflectionMethod(AnalysisPathController::class, 'findFirstMatchOffset');

        return $reflectionMethod->invoke(new AnalysisPathController(), $tokenOffsets, $token);
    }
}
