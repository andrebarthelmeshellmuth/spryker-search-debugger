<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Controller;

use Codeception\Test\Unit;
use ReflectionMethod;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Yves\SearchDebugWidget\Controller\AnalysisPathController;
use Symfony\Component\HttpFoundation\Request;

/**
 * `indexAction()` itself needs a real Silex/Symfony container (`$this->can()`, `$this->getFactory()`,
 * `$this->view()`) and is left to integration coverage — but the three protected helpers it calls have no
 * framework dependency at all, so they are exercised directly here via reflection, `new`-instantiating the
 * controller (`AbstractController` has no required constructor arguments).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Controller
 * @group AnalysisPathControllerTest
 * Add your own group annotations below this line
 */
class AnalysisPathControllerTest extends Unit
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testResolveUseSearchAnalyzerReturnsFalseWhenTheAnalyzerParameterIsAbsent(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertFalse($useSearchAnalyzer);
    }

    /**
     * @return void
     */
    public function testResolveUseSearchAnalyzerReturnsFalseForAnyUnrecognizedValue(): void
    {
        // Arrange
        $request = new Request(['analyzer' => 'index']);

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertFalse($useSearchAnalyzer);
    }

    /**
     * @return void
     */
    public function testResolveUseSearchAnalyzerReturnsTrueForTheRecognizedSearchValue(): void
    {
        // Arrange
        $request = new Request(['analyzer' => 'search']);

        // Act
        $useSearchAnalyzer = $this->invokeResolveUseSearchAnalyzer($request);

        // Assert
        $this->assertTrue($useSearchAnalyzer);
    }

    /**
     * @return void
     */
    public function testResolveExplicitOffsetReturnsNullWhenBothOffsetParametersAreMissing(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    /**
     * @return void
     */
    public function testResolveExplicitOffsetReturnsNullWhenOnlyOneOffsetParameterIsPresent(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '0']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    /**
     * @return void
     */
    public function testResolveExplicitOffsetReturnsNullWhenTheStartOffsetIsNegative(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '-1', 'endOffset' => '5']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    /**
     * @return void
     */
    public function testResolveExplicitOffsetReturnsNullWhenTheEndOffsetDoesNotExceedTheStartOffset(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '5', 'endOffset' => '5']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertNull($offset);
    }

    /**
     * @return void
     */
    public function testResolveExplicitOffsetReturnsTheOffsetsWhenBothAreValid(): void
    {
        // Arrange
        $request = new Request(['startOffset' => '4', 'endOffset' => '9']);

        // Act
        $offset = $this->invokeResolveExplicitOffset($request);

        // Assert
        $this->assertSame(['startOffset' => 4, 'endOffset' => 9], $offset);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
     *
     * @return bool
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
