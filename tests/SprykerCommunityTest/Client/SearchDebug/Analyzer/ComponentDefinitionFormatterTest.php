<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Analyzer;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;
use SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatter;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Analyzer
 * @group ComponentDefinitionFormatterTest
 * Add your own group annotations below this line
 */
class ComponentDefinitionFormatterTest extends Unit
{
    public function testFormatReturnsNullWhenTheComponentIsNull(): void
    {
        // Act
        $definition = (new ComponentDefinitionFormatter())->format(null);

        // Assert
        $this->assertNull($definition);
    }

    public function testFormatReturnsJustTheTypeWhenThereIsNoFurtherConfig(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_lowercase')
            ->setType('lowercase')
            ->setConfig([]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(['label' => 'lowercase', 'truncated' => false], $formatted);
    }

    /**
     * The real `page.json` shape for this shop's one custom filter.
     */
    public function testFormatIncludesScalarConfigParametersInOrder(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('fulltext_index_ngram_filter')
            ->setType('edge_ngram')
            ->setConfig(['min_gram' => 2, 'max_gram' => 20]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(
            ['label' => 'edge_ngram (min_gram: 2, max_gram: 20)', 'truncated' => false],
            $formatted,
        );
    }

    /**
     * PHP casts `true`/`false` to `"1"`/`""` on a naive `(string)` cast — the empty string would read as
     * a missing value, not `false`, so this must be spelled out explicitly. `word_delimiter` is a common
     * real-world filter with exactly this shape (`generate_word_parts`, `split_on_numerics`, ...).
     */
    public function testFormatSpellsOutBooleanConfigValues(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_word_delimiter')
            ->setType('word_delimiter')
            ->setConfig(['generate_word_parts' => true, 'split_on_numerics' => false]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(
            ['label' => 'word_delimiter (generate_word_parts: true, split_on_numerics: false)', 'truncated' => false],
            $formatted,
        );
    }

    /**
     * A short list (at or under the preview limit) is shown in full — not truncated, so no link to a
     * full-list page is warranted.
     */
    public function testFormatShowsAShortListInFullAndReportsNoTruncation(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_stop')
            ->setType('stop')
            ->setConfig(['stopwords' => ['a', 'the']]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(['label' => 'stop (stopwords: a, the)', 'truncated' => false], $formatted);
    }

    /**
     * A naive `(string)` cast turns `true`/`false` INSIDE a list into `"1"`/`""` (PHP's own scalar-cast
     * quirk) — the empty string would then read as a missing/blank item, not `false`. Each list item goes
     * through the same shared formatter {@see testFormatSpellsOutBooleanConfigValues()} covers for a
     * top-level config value, so this must hold for list items too.
     */
    public function testFormatShowsAShortListInFullAndSpellsOutBooleansWithinIt(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_condition')
            ->setType('condition')
            ->setConfig(['flags' => [true, false, 'a']]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(['label' => 'condition (flags: true, false, a)', 'truncated' => false], $formatted);
    }

    /**
     * A real `synonym`/`stop` word list can run into the hundreds — dumping it verbatim would turn one
     * debug line into an unreadable blob, so only a preview is shown, with the total count appended, and
     * `truncated` reports true so a caller can offer a link to the full, untruncated list.
     */
    public function testFormatTruncatesALongListAppendsTheTotalCountAndReportsTruncation(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_stop')
            ->setType('stop')
            ->setConfig(['stopwords' => ['a', 'an', 'the', 'and', 'or', 'but', 'so']]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(
            ['label' => 'stop (stopwords: a, an, the, and, or, … (7 total))', 'truncated' => true],
            $formatted,
        );
    }

    /**
     * An empty list still reports itself as an (intentionally) empty list, not silently vanishing, and
     * is obviously not truncated.
     */
    public function testFormatShowsAnEmptyListExplicitly(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_stop')
            ->setType('stop')
            ->setConfig(['stopwords' => []]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(['label' => 'stop (stopwords: [])', 'truncated' => false], $formatted);
    }

    /**
     * Belt-and-suspenders on top of the list preview: even after truncating list VALUES, the whole
     * formatted line still gets one final hard character limit — covers a single long scalar (e.g. a
     * long regex `pattern`) that the list-specific truncation above never sees. Still reports
     * `truncated` so a caller can offer a link to the untruncated value.
     */
    public function testFormatTruncatesAnOverallLongDefinitionWithAnEllipsisAndReportsTruncation(): void
    {
        // Arrange
        $component = (new SearchAnalysisComponentTransfer())
            ->setName('my_pattern_replace')
            ->setType('pattern_replace')
            ->setConfig(['pattern' => str_repeat('a', 300)]);

        // Act
        $formatted = (new ComponentDefinitionFormatter())->format($component);

        // Assert
        $this->assertSame(220, mb_strlen($formatted['label']));
        $this->assertStringEndsWith('…', $formatted['label']);
        $this->assertTrue($formatted['truncated']);
    }
}
