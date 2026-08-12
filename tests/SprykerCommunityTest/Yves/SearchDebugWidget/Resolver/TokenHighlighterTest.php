<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Resolver;

use Codeception\Test\Unit;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighter;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group TokenHighlighterTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Yves\SearchDebugWidget\SearchDebugWidgetTester $tester
 * @group Portable
 */
class TokenHighlighterTest extends Unit
{
    public function testHighlightWrapsASingleMatchInAMarkTag(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('a cable is here', [
            ['startOffset' => 2, 'endOffset' => 7],
        ]);

        // Assert
        $this->assertSame('a <mark class="search-debug-highlight">cable</mark> is here', $html);
    }

    public function testHighlightWrapsMultipleNonOverlappingMatches(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('cable and more cable', [
            ['startOffset' => 15, 'endOffset' => 20],
            ['startOffset' => 0, 'endOffset' => 5],
        ]);

        // Assert
        $this->assertSame(
            '<mark class="search-debug-highlight">cable</mark> and more <mark class="search-debug-highlight">cable</mark>',
            $html,
        );
    }

    public function testHighlightEscapesHtmlSpecialCharactersInBothMatchedAndUnmatchedText(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('<b>cable</b> & more', [
            ['startOffset' => 3, 'endOffset' => 8],
        ]);

        // Assert
        $this->assertSame(
            '&lt;b&gt;<mark class="search-debug-highlight">cable</mark>&lt;/b&gt; &amp; more',
            $html,
        );
    }

    public function testHighlightReturnsEscapedTextUnchangedWhenThereAreNoMatches(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('no matches here', []);

        // Assert
        $this->assertSame('no matches here', $html);
    }

    /**
     * A match spanning the very start and end of the text must not lose any characters at the boundary.
     */
    public function testHighlightHandlesAMatchAtTheTextBoundaries(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('cable', [
            ['startOffset' => 0, 'endOffset' => 5],
        ]);

        // Assert
        $this->assertSame('<mark class="search-debug-highlight">cable</mark>', $html);
    }

    /**
     * Elasticsearch offsets are UTF-16 code units (Java string indexing): the emoji below counts as TWO
     * units, so "cable" spans units 3-8 — while a code-point-based slicer would place it at 2-7 and shift
     * the highlight one character left. German umlauts (single code unit each) must stay exact too.
     */
    public function testHighlightTreatsOffsetsAsUtf16CodeUnits(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('🔥 cable für Öfen', [
            ['startOffset' => 3, 'endOffset' => 8],
        ]);

        // Assert
        $this->assertSame('🔥 <mark class="search-debug-highlight">cable</mark> für Öfen', $html);
    }

    /**
     * A plain (non-edge) `ngram` filter with min_gram=max_gram=2 on repeated-character text, e.g. "aaaa",
     * yields pairwise-overlapping tokens at offsets [0,2), [1,3), [2,4) — the overlapping ones (here, the
     * second and third) must be dropped rather than producing malformed/nested `<mark>` tags.
     */
    public function testHighlightSkipsAMatchThatOverlapsAnEarlierOne(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $html = $tokenHighlighter->highlight('aaaa', [
            ['startOffset' => 0, 'endOffset' => 2],
            ['startOffset' => 1, 'endOffset' => 3],
            ['startOffset' => 2, 'endOffset' => 4],
        ]);

        // Assert
        $this->assertSame(
            '<mark class="search-debug-highlight">aa</mark><mark class="search-debug-highlight">aa</mark>',
            $html,
        );
    }

    public function testFilterRenderableKeepsNonOverlappingMatchesInStartOffsetOrder(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $renderable = $tokenHighlighter->filterRenderable([
            ['startOffset' => 15, 'endOffset' => 20],
            ['startOffset' => 0, 'endOffset' => 5],
        ]);

        // Assert
        $this->assertSame(
            [
                ['startOffset' => 0, 'endOffset' => 5],
                ['startOffset' => 15, 'endOffset' => 20],
            ],
            $renderable,
        );
    }

    /**
     * The exact rule {@see testHighlightSkipsAMatchThatOverlapsAnEarlierOne()} exercises through
     * `highlight()` — asserted directly here so a caller keeping its OWN separate list of matches (e.g.
     * one link built per rendered mark) can rely on `filterRenderable()` alone to know which matches
     * `highlight()` will actually render, without rendering any HTML at all.
     */
    public function testFilterRenderableDropsAMatchThatOverlapsAnEarlierOne(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $renderable = $tokenHighlighter->filterRenderable([
            ['startOffset' => 0, 'endOffset' => 2],
            ['startOffset' => 1, 'endOffset' => 3],
            ['startOffset' => 2, 'endOffset' => 4],
        ]);

        // Assert
        $this->assertSame(
            [
                ['startOffset' => 0, 'endOffset' => 2],
                ['startOffset' => 2, 'endOffset' => 4],
            ],
            $renderable,
        );
    }

    public function testFilterRenderableReturnsAnEmptyArrayForNoMatches(): void
    {
        // Arrange
        $tokenHighlighter = new TokenHighlighter();

        // Act
        $renderable = $tokenHighlighter->filterRenderable([]);

        // Assert
        $this->assertSame([], $renderable);
    }
}
