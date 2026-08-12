<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Utf16;

use Codeception\Test\Unit;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Utf16
 * @group Utf16CodeUnitConverterTest
 * Add your own group annotations below this line
 * @group Portable
 */
class Utf16CodeUnitConverterTest extends Unit
{
    public function testLengthOfCountsCodeUnitsNotBytes(): void
    {
        // Arrange
        $converter = new Utf16CodeUnitConverter();
        $textUtf16 = $converter->toUtf16('cable');

        // Assert
        $this->assertSame(5, $converter->lengthOf($textUtf16));
    }

    /**
     * A basic-multilingual-plane character (e.g. "é") still occupies exactly one UTF-16 code unit, same
     * as any ASCII character — only supplementary-plane characters (emoji) need two.
     */
    public function testLengthOfCountsABasicMultilingualPlaneCharacterAsOneCodeUnit(): void
    {
        // Arrange
        $converter = new Utf16CodeUnitConverter();
        $textUtf16 = $converter->toUtf16('café');

        // Assert
        $this->assertSame(4, $converter->lengthOf($textUtf16));
    }

    /**
     * A supplementary-plane character (here: an emoji outside the BMP) is encoded as a UTF-16 surrogate
     * pair — two code units — which is the entire reason this converter exists instead of using
     * `mb_strlen()` directly against a byte offset supplied by Elasticsearch's highlighter.
     */
    public function testLengthOfCountsASupplementaryPlaneCharacterAsTwoCodeUnits(): void
    {
        // Arrange
        $converter = new Utf16CodeUnitConverter();
        $textUtf16 = $converter->toUtf16('🔌plug');

        // Assert
        $this->assertSame(6, $converter->lengthOf($textUtf16));
    }

    public function testSliceExtractsAContiguousRangeOfCodeUnitsAndReturnsItAsUtf8(): void
    {
        // Arrange
        $converter = new Utf16CodeUnitConverter();
        $textUtf16 = $converter->toUtf16('cable tie');

        // Act
        $slice = $converter->slice($textUtf16, 0, 5);

        // Assert
        $this->assertSame('cable', $slice);
    }

    /**
     * Slicing around a supplementary-plane character must land on the surrogate-pair boundary, not split
     * it in half — the exact failure mode a naive byte-offset `substr()` would produce.
     */
    public function testSliceHandlesASupplementaryPlaneCharacterWithoutSplittingItsSurrogatePair(): void
    {
        // Arrange
        $converter = new Utf16CodeUnitConverter();
        $textUtf16 = $converter->toUtf16('🔌plug');

        // Act
        $emojiSlice = $converter->slice($textUtf16, 0, 2);
        $restSlice = $converter->slice($textUtf16, 2, 6);

        // Assert
        $this->assertSame('🔌', $emojiSlice);
        $this->assertSame('plug', $restSlice);
    }
}
