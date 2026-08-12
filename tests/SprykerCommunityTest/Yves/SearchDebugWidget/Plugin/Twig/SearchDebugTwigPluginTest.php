<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Plugin\Twig;

use Codeception\Test\Unit;
use ReflectionMethod;
use Spryker\Service\Container\ContainerInterface;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Yves\SearchDebug\Plugin\Twig\SearchDebugTwigPlugin;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * `extend()` itself only registers a function (checked once, directly); the actual color-assignment
 * logic in `getTokenColorClasses()` has no framework dependency at all, so it is exercised directly via
 * reflection, same approach {@see \SprykerCommunityTest\Yves\SearchDebugWidget\Controller\AnalysisPathControllerTest}
 * uses for its own framework-free helpers.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Plugin
 * @group Twig
 * @group SearchDebugTwigPluginTest
 * Add your own group annotations below this line
 * @group Portable
 */
class SearchDebugTwigPluginTest extends Unit
{
    public function testExtendRegistersTheTokenColorsFunction(): void
    {
        // Arrange
        $plugin = new SearchDebugTwigPlugin();
        $twig = new Environment(new ArrayLoader());

        // Act
        $extendedTwig = $plugin->extend($twig, $this->createMock(ContainerInterface::class));

        // Assert
        $this->assertTrue($extendedTwig->getFunction(SearchDebugTwigPlugin::FUNCTION_NAME_TOKEN_COLORS) !== false);
    }

    public function testGetTokenColorClassesReturnsEmptyArrayForNoTokens(): void
    {
        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses([]);

        // Assert
        $this->assertSame([], $colorClassByToken);
    }

    public function testGetTokenColorClassesGivesTheFirstTokenTheFirstPaletteColor(): void
    {
        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses(['cable']);

        // Assert
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 1), $colorClassByToken['cable']);
    }

    public function testGetTokenColorClassesGivesEachTokenItsOwnSequentialColor(): void
    {
        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses(['office', 'chair']);

        // Assert
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 1), $colorClassByToken['office']);
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 2), $colorClassByToken['chair']);
    }

    public function testGetTokenColorClassesWrapsAroundPastThePaletteSize(): void
    {
        // Arrange — one more distinct token than the palette has colors for.
        $tokens = [];
        for ($index = 0; $index < SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT + 1; $index++) {
            $tokens[] = 'token-' . $index;
        }

        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses($tokens);

        // Assert — the (COUNT + 1)th token wraps back around to the first color.
        $this->assertSame($colorClassByToken['token-0'], $colorClassByToken['token-' . SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT]);
    }

    /**
     * The result is keyed by token text, so a repeated token collapses to one entry — its position is
     * whichever occurrence `array_values()` re-indexes it to during iteration, not necessarily the first.
     * Documented here as real, current behavior rather than left as an untested surprise.
     */
    public function testGetTokenColorClassesCollapsesARepeatedTokenToOneEntry(): void
    {
        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses(['cable', 'chair', 'cable']);

        // Assert
        $this->assertCount(2, $colorClassByToken);
        $this->assertArrayHasKey('cable', $colorClassByToken);
        $this->assertArrayHasKey('chair', $colorClassByToken);
    }

    /**
     * `array_values()` inside the plugin re-indexes non-sequential/associative input before assigning
     * colors — an associative `$queryTokens` array must not throw or skip entries.
     */
    public function testGetTokenColorClassesHandlesNonSequentialInputKeys(): void
    {
        // Act
        $colorClassByToken = $this->invokeGetTokenColorClasses([5 => 'cable', 9 => 'chair']);

        // Assert
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 1), $colorClassByToken['cable']);
        $this->assertSame(sprintf(SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN, 2), $colorClassByToken['chair']);
    }

    /**
     * @param array<string> $queryTokens
     *
     * @return array<string, string>
     */
    protected function invokeGetTokenColorClasses(array $queryTokens): array
    {
        $reflectionMethod = new ReflectionMethod(SearchDebugTwigPlugin::class, 'getTokenColorClasses');

        return $reflectionMethod->invoke(new SearchDebugTwigPlugin(), $queryTokens);
    }
}
