<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Resolver;

use Codeception\Test\Unit;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatter;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group ComponentConfigFormatterTest
 * Add your own group annotations below this line
 */
class ComponentConfigFormatterTest extends Unit
{
    /**
     * @return void
     */
    public function testFormatKeepsScalarValuesAsStrings(): void
    {
        // Act
        $formatted = (new ComponentConfigFormatter())->format(['min_gram' => 2, 'max_gram' => 20]);

        // Assert
        $this->assertSame(['min_gram' => '2', 'max_gram' => '20'], $formatted);
    }

    /**
     * PHP casts `true`/`false` to `"1"`/`""` on a naive `(string)` cast — the empty string would read as
     * a missing value, not `false`.
     *
     * @return void
     */
    public function testFormatSpellsOutBooleanValues(): void
    {
        // Act
        $formatted = (new ComponentConfigFormatter())->format([
            'generate_word_parts' => true,
            'split_on_numerics' => false,
        ]);

        // Assert
        $this->assertSame(['generate_word_parts' => 'true', 'split_on_numerics' => 'false'], $formatted);
    }

    /**
     * A list-valued config key (e.g. `stopwords`, `synonyms`) shows EVERY item, formatted safely — this
     * page exists specifically to show what the short inline preview truncated away.
     *
     * @return void
     */
    public function testFormatKeepsEveryListItemAndSpellsOutBooleansWithinIt(): void
    {
        // Act
        $formatted = (new ComponentConfigFormatter())->format([
            'stopwords' => ['a', 'an', 'the'],
            'flags' => [true, false],
        ]);

        // Assert
        $this->assertSame(
            [
                'stopwords' => ['a', 'an', 'the'],
                'flags' => ['true', 'false'],
            ],
            $formatted,
        );
    }

    /**
     * A nested array item (e.g. a `multiplexer` filter's own sub-filter chain) can't print directly in
     * Twig at all — JSON-encoded rather than crashing the page.
     *
     * @return void
     */
    public function testFormatJsonEncodesANestedArrayItem(): void
    {
        // Act
        $formatted = (new ComponentConfigFormatter())->format([
            'filters' => [['type' => 'lowercase']],
        ]);

        // Assert
        $this->assertSame(['filters' => ['{"type":"lowercase"}']], $formatted);
    }

    /**
     * @return void
     */
    public function testFormatReturnsAnEmptyArrayForAnEmptyConfig(): void
    {
        // Act
        $formatted = (new ComponentConfigFormatter())->format([]);

        // Assert
        $this->assertSame([], $formatted);
    }
}
