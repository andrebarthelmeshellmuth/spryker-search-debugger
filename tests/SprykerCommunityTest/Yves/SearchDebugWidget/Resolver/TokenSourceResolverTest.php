<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Resolver;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\CategoryNodeStorageTransfer;
use Generated\Shared\Transfer\MerchantStorageTransfer;
use Generated\Shared\Transfer\ProductAbstractCategoryStorageTransfer;
use Generated\Shared\Transfer\ProductCategoryStorageTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\CategoryAncestorNameCollector;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ProductSourceMapBuilder;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolver;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceRow;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group TokenSourceResolverTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Yves\SearchDebugWidget\SearchDebugWidgetTester $tester
 * @group Portable
 */
class TokenSourceResolverTest extends Unit
{
    /**
     * @var string
     */
    protected const TOKEN = 'cable';

    /**
     * The exact offset pair {@see createSearchElasticsearchClientMock()}'s `getTextTokenOffsetsForTexts()` stub
     * always returns for a matching text — every "matched" row's expected `matches` value in this file.
     *
     * @var array<int, array{token: string, startOffset: int, endOffset: int}>
     */
    protected const TOKEN_MATCHES = [['token' => self::TOKEN, 'startOffset' => 0, 'endOffset' => 5]];

    /**
     * @var string
     */
    protected const PRODUCT_ABSTRACT_SKU = 'ABSTRACT-SKU-1';

    /**
     * A typical two-tier $fieldBoosts, passed explicitly wherever a test needs real tier data — the
     * resolver itself has no fallback (and no notion of a "default" boost) since $fieldBoosts always
     * comes from the real query now. Deliberately NOT the real configured value (3, see
     * `config/Shared/config_default.php`), so a test that hardcoded 3 instead of reading this constant
     * wouldn't accidentally still pass.
     *
     * @var array<string, int>
     */
    protected const FIELD_BOOSTS = ['full-text-boosted' => 7, 'full-text' => 1];

    public function testResolveReturnsNullWhenNoProductAbstractHasThatSku(): void
    {
        // Arrange
        $productStorageClientMock = $this->createMock(ProductStorageClientInterface::class);
        $productStorageClientMock->method('findProductAbstractStorageDataByMapping')->willReturn(null);

        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->expects($this->never())->method('findPageDocumentData');

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Rows come from the REAL indexed document: identified elements get their source label, the token
     * test runs per element, and matched elements are highlighted while unmatched identified ones show
     * as a compact "no match" (highlightedHtml = null).
     */
    public function testResolveBuildsRowsFromTheRealDocumentElements(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
            'description' => 'A cable for outdoor use',
        ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Steel Cable', 'STEEL-1'],
                'full-text' => ['A cable for outdoor use'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertSame('Steel Cable', $result['productTitle']);
        $this->assertSame('STEEL-1', $result['productSku']);

        $boostedRows = $result['tiers'][0]['rows'];
        $this->assertSame('full-text-boosted', $result['tiers'][0]['key']);
        $this->assertSame(static::FIELD_BOOSTS['full-text-boosted'], $result['tiers'][0]['boost']);
        $this->assertEquals(
            [
                new TokenSourceRow(['search_debug.token_source.field.title'], true, false, 'HL[Steel Cable]', 'Steel Cable', static::TOKEN_MATCHES),
                new TokenSourceRow(['search_debug.token_source.field.sku'], false, false, null, null, []),
            ],
            $boostedRows,
        );

        $fullTextRows = $result['tiers'][1]['rows'];
        $this->assertSame('full-text', $result['tiers'][1]['key']);
        $this->assertSame(1, $result['tiers'][1]['boost']);
        $this->assertEquals(
            [
                new TokenSourceRow(['search_debug.token_source.field.abstract_description'], true, false, 'HL[A cable for outdoor use]', 'A cable for outdoor use', static::TOKEN_MATCHES),
            ],
            $fullTextRows,
        );
    }

    /**
     * A document element no known source claims (e.g. a searchable product attribute mapped via
     * Zed > Search Preferences) must still surface — under the generic "other" label, and ALWAYS with
     * its text: for an unidentified value, the value itself is the diagnostic information.
     */
    public function testResolveLabelsUnidentifiedElementsAsOtherAndAlwaysShowsTheirText(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'CABLE-1',
        ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['rot', 'cable-ish attribute'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertEquals(
            [
                new TokenSourceRow(['search_debug.token_source.field.other'], false, true, 'TXT[rot]', null, []),
                new TokenSourceRow(['search_debug.token_source.field.other'], true, true, 'HL[cable-ish attribute]', 'cable-ish attribute', static::TOKEN_MATCHES),
            ],
            $result['tiers'][0]['rows'],
        );
    }

    /**
     * A source that contributed nothing to the document gets no row at all — that is more accurate than
     * a "no match" row, which would imply the value was indexed and merely didn't contain the token.
     */
    public function testResolveOmitsSourcesAbsentFromTheDocument(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'CABLE-1',
        ]);

        // Document contains only the title element — no sku element, although the product has a SKU.
        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Steel Cable'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $labelKeys = array_column($result['tiers'][0]['rows'], 'labelKeys');
        $this->assertSame([['search_debug.token_source.field.title']], $labelKeys);
    }

    /**
     * When two DIFFERENT named sources contribute the identical text to the same tier (e.g. a merchant
     * named the same as the product), that text is genuinely indistinguishable once merged into one
     * document element — the row must list BOTH source labels (labelKeys has more than one entry, in
     * canonical order) rather than silently picking the first and dropping the other.
     */
    public function testResolveShowsBothLabelsWhenTwoSourcesContributeIdenticalText(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Acme',
            'sku' => 'STEEL-1',
            'merchant_reference' => 'MER000005',
        ]);

        $merchantStorageClientMock = $this->createMock(MerchantStorageClientInterface::class);
        $merchantStorageClientMock->method('findOne')
            ->willReturn((new MerchantStorageTransfer())->setName('Acme'));

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Acme', 'STEEL-1'],
            ],
        );

        $resolver = $this->createResolver(
            $productStorageClientMock,
            $searchDebugClientMock,
            null,
            null,
            $merchantStorageClientMock,
        );

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertEquals(
            [
                new TokenSourceRow(
                    ['search_debug.token_source.field.title', 'search_debug.token_source.field.merchant_name'],
                    false,
                    false,
                    null,
                    null,
                    [],
                ),
                new TokenSourceRow(['search_debug.token_source.field.sku'], false, false, null, null, []),
            ],
            $result['tiers'][0]['rows'],
        );
    }

    /**
     * A source contributed by multiple document elements (e.g. concrete names — one per variant) shows
     * one row PER MATCHED element, each carrying its own raw `element` text, rather than combining them
     * into a single box — a matched fragment's magnifying-glass link needs its own exact source text.
     */
    public function testResolveShowsOneRowPerMatchedElementWhenASourceHasMultipleMatchingElements(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'attribute_map' => ['product_concrete_ids' => ['CONCRETE-1' => 11, 'CONCRETE-2' => 12]],
        ]);
        $productStorageClientMock->method('getBulkProductConcreteStorageData')
            ->with([11, 12], 'en_US')
            ->willReturn([
                ['name' => 'Cable A', 'sku' => 'CONCRETE-1', 'description' => ''],
                ['name' => 'Cable B', 'sku' => 'CONCRETE-2', 'description' => ''],
            ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text' => ['Cable A', 'Cable B'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertEquals(
            [
                new TokenSourceRow(['search_debug.token_source.field.concrete_names'], true, false, 'HL[Cable A]', 'Cable A', static::TOKEN_MATCHES),
                new TokenSourceRow(['search_debug.token_source.field.concrete_names'], true, false, 'HL[Cable B]', 'Cable B', static::TOKEN_MATCHES),
            ],
            $result['tiers'][1]['rows'],
        );
    }

    public function testResolveReturnsEmptyRowsWhenTheDocumentIsMissing(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'CABLE-1',
        ]);

        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('findPageDocumentData')->willReturn(null);
        $searchDebugClientMock->expects($this->never())->method('getTextTokenOffsetsForTexts');

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame([], $result['tiers'][0]['rows']);
        $this->assertSame([], $result['tiers'][1]['rows']);
    }

    /**
     * Identical element strings recur within one document (e.g. a concrete name equal to the abstract
     * name, indexed into both tiers) — each distinct string must be analyzed exactly once.
     */
    public function testResolveAnalyzesEachDistinctElementOnlyOnce(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'CABLE-1',
        ]);

        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('findPageDocumentData')->willReturn([
            'full-text-boosted' => ['Steel Cable', 'CABLE-1'],
            'full-text' => ['Steel Cable', 'Steel Cable'],
        ]);

        // One batched call for the whole document (see TokenSourceResolver::warmTokenOffsetsCache()),
        // not one per element — and the texts it receives are already deduplicated across BOTH tiers,
        // "Steel Cable" appearing three times across the document but only once in the call.
        $searchDebugClientMock->expects($this->once())
            ->method('getTextTokenOffsetsForTexts')
            ->with($this->callback(function (array $texts): bool {
                $this->assertSame(['Steel Cable', 'CABLE-1'], $texts);

                return true;
            }))
            ->willReturn([
                'Steel Cable' => [['token' => static::TOKEN, 'startOffset' => 0, 'endOffset' => 5]],
                'CABLE-1' => [],
            ]);

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);
    }

    /**
     * A document element that happens to read as a bare number (e.g. a batch number like "400065",
     * confirmed live in this shop's own product data) must survive as a real PHP string all the way to
     * the batched analyze call. PHP silently coerces a purely-numeric STRING array KEY to an int key —
     * building the pre-fetch list as `$dict[$element] = true` followed by `array_keys($dict)` (an earlier,
     * real, live-reproduced bug in `TokenSourceResolver::warmTokenOffsetsCache()`) leaked an `int` into
     * the texts array, which crashed downstream with a `TypeError` — `string` expected, `int` given —
     * once it reached `Utf16CodeUnitConverter::toUtf16()`. `array_unique()` over a plain list (the actual
     * fix) never touches keys, so this must never regress.
     */
    public function testResolveKeepsANumericLookingElementAsARealStringInTheBatchedCall(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'CABLE-1',
        ]);

        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('findPageDocumentData')->willReturn([
            'full-text-boosted' => ['Steel Cable', '400065'],
        ]);

        $searchDebugClientMock->expects($this->once())
            ->method('getTextTokenOffsetsForTexts')
            ->with($this->callback(function (array $texts): bool {
                foreach ($texts as $text) {
                    $this->assertIsString($text, 'every element passed to the batched analyze call must be a real string, never an int-coerced array key');
                }

                $this->assertSame(['Steel Cable', '400065'], $texts);

                return true;
            }))
            ->willReturn([
                'Steel Cable' => [['token' => static::TOKEN, 'startOffset' => 0, 'endOffset' => 5]],
                '400065' => [],
            ]);

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);
    }

    /**
     * Concrete variants, categories (direct + recursive ancestors), and the merchant name feed the
     * per-tier value-to-source lookup, so their document elements resolve to the right labels.
     */
    public function testResolveLabelsConcreteCategoryAndMerchantValues(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'merchant_reference' => 'MER000005',
            'attribute_map' => ['product_concrete_ids' => ['CONCRETE-1' => 11]],
        ]);
        $productStorageClientMock->method('getBulkProductConcreteStorageData')
            ->with([11], 'en_US')
            ->willReturn([
                ['name' => 'Cable Red', 'sku' => 'CONCRETE-1', 'description' => 'Red variant'],
            ]);

        $directCategory = (new ProductCategoryStorageTransfer())->setName('Cables')->setCategoryNodeId(5);
        $productCategoryStorageClientMock = $this->createMock(ProductCategoryStorageClientInterface::class);
        $productCategoryStorageClientMock->method('findBulkProductAbstractCategory')
            ->willReturn([(new ProductAbstractCategoryStorageTransfer())->addCategory($directCategory)]);

        $grandparent = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('All Products');
        $parent = (new CategoryNodeStorageTransfer())->setNodeId(2)->setName('Electrical')->addParents($grandparent);
        $directNode = (new CategoryNodeStorageTransfer())->setNodeId(5)->setName('Cables')->addParents($parent);
        $categoryStorageClientMock = $this->createMock(CategoryStorageClientInterface::class);
        $categoryStorageClientMock->method('getCategoryNodeByIds')->with([5], 'en_US', 'DE')->willReturn([5 => $directNode]);

        $merchantStorageClientMock = $this->createMock(MerchantStorageClientInterface::class);
        $merchantStorageClientMock->method('findOne')
            ->willReturn((new MerchantStorageTransfer())->setName('Video King'));

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Cable', 'ABSTRACT-1', 'Cables', 'Video King'],
                'full-text' => ['Cable Red', 'CONCRETE-1', 'Red variant', 'Electrical', 'All Products'],
            ],
        );

        $resolver = $this->createResolver(
            $productStorageClientMock,
            $searchDebugClientMock,
            $productCategoryStorageClientMock,
            $categoryStorageClientMock,
            $merchantStorageClientMock,
        );

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertSame(
            [
                ['search_debug.token_source.field.title'],
                ['search_debug.token_source.field.sku'],
                ['search_debug.token_source.field.direct_categories'],
                ['search_debug.token_source.field.merchant_name'],
            ],
            array_column($result['tiers'][0]['rows'], 'labelKeys'),
        );
        $this->assertSame(
            [
                ['search_debug.token_source.field.concrete_names'],
                ['search_debug.token_source.field.concrete_skus'],
                ['search_debug.token_source.field.concrete_descriptions'],
                ['search_debug.token_source.field.indirect_categories'],
            ],
            array_column($result['tiers'][1]['rows'], 'labelKeys'),
        );
    }

    /**
     * Tiers are driven entirely by $fieldBoosts — however many fields the query actually searched, sorted
     * boost-descending — not a hardcoded two-tier list. A field not covered by `TIER_LABEL_KEYS` (any
     * `PageIndexMap` field other than the two well-known ones) still gets its own tier, labeled with the
     * generic fallback rather than dropped.
     */
    public function testResolveBuildsTiersFromFieldBoostsSortedDescendingWithGenericLabelForAnUnknownField(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
        ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Steel Cable'],
                'custom-field' => ['a custom value'],
                'full-text' => ['a description'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(
            static::PRODUCT_ABSTRACT_SKU,
            static::TOKEN,
            'en_US',
            ['full-text' => 1, 'full-text-boosted' => 5, 'custom-field' => 3],
        );

        // Assert
        $this->assertSame(['full-text-boosted', 'custom-field', 'full-text'], array_column($result['tiers'], 'key'));
        $this->assertSame([5, 3, 1], array_column($result['tiers'], 'boost'));
        $this->assertSame(
            [
                'search_debug.token_source.tier.full_text_boosted',
                'search_debug.token_source.tier.generic',
                'search_debug.token_source.tier.full_text',
            ],
            array_column($result['tiers'], 'labelKey'),
        );
    }

    /**
     * No fallback: an empty (or omitted) $fieldBoosts means the fields a query actually used could not
     * be captured, so `tiers` comes back empty rather than guessing at a field list or a boost value
     * that isn't real — and the document is never even probed for tier content, since there is nothing
     * to look for without knowing which fields to look at.
     */
    public function testResolveReturnsNoTiersWhenFieldBoostsIsEmpty(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
        ]);

        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('findPageDocumentData')->willReturn([
            'full-text-boosted' => ['Steel Cable'],
            'full-text' => ['a description'],
        ]);
        $searchDebugClientMock->expects($this->never())->method('getTextTokenOffsetsForTexts');

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', []);

        // Assert
        $this->assertSame([], $result['tiers']);
    }

    /**
     * A document element that no named `SOURCE_DEFINITIONS` source claims is checked against the
     * product's own attribute values next: if it matches one, the row is labeled with the raw attribute
     * key (e.g. "brand") instead of falling straight to the generic "other" label.
     */
    public function testResolveLabelsAnUnidentifiedElementWithItsAttributeKeyWhenItMatchesAProductAttribute(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
            'attributes' => ['brand' => 'Acme'],
        ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['Acme'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertEquals(
            [
                new TokenSourceRow(['brand'], false, false, 'TXT[Acme]', null, []),
            ],
            $result['tiers'][0]['rows'],
        );
    }

    /**
     * A document element neither a named source NOR any product attribute value claims still falls back
     * to the generic "other indexed value" label — this existing behavior must keep working once
     * attribute-based labeling is checked first.
     */
    public function testResolveStillLabelsAnElementAsOtherWhenNoAttributeValueMatchesEither(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
            'attributes' => ['brand' => 'Acme'],
        ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text-boosted' => ['some unrelated value'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $this->assertEquals(
            [
                new TokenSourceRow(['search_debug.token_source.field.other'], false, true, 'TXT[some unrelated value]', null, []),
            ],
            $result['tiers'][0]['rows'],
        );
    }

    /**
     * Concrete-level attribute values (from `getBulkProductConcreteStorageData()`) feed the same
     * value=>attributeKey map as the abstract-level `attributes`, so a value only present on a concrete
     * variant is still labeled with its real attribute key rather than falling to "other".
     */
    public function testResolveLabelsAnUnidentifiedElementWithAConcreteLevelAttributeKey(): void
    {
        // Arrange
        $productStorageClientMock = $this->createProductStorageClientMock([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'attribute_map' => ['product_concrete_ids' => ['CONCRETE-1' => 11]],
        ]);
        $productStorageClientMock->method('getBulkProductConcreteStorageData')
            ->with([11], 'en_US')
            ->willReturn([
                ['name' => 'Cable Red', 'sku' => 'CONCRETE-1', 'description' => '', 'attributes' => ['color' => 'Red']],
            ]);

        $searchDebugClientMock = $this->createSearchElasticsearchClientMock(
            [
                'full-text' => ['Cable Red', 'CONCRETE-1', 'Red'],
            ],
        );

        $resolver = $this->createResolver($productStorageClientMock, $searchDebugClientMock);

        // Act
        $result = $resolver->resolve(static::PRODUCT_ABSTRACT_SKU, static::TOKEN, 'en_US', static::FIELD_BOOSTS);

        // Assert
        $labelKeys = array_merge(...array_column($result['tiers'][1]['rows'], 'labelKeys'));
        $this->assertContains('color', $labelKeys);
    }

    /**
     * @param array<string, mixed> $productData
     */
    protected function createProductStorageClientMock(array $productData): ProductStorageClientInterface
    {
        $productStorageClientMock = $this->createMock(ProductStorageClientInterface::class);
        $productStorageClientMock->method('findProductAbstractStorageDataByMapping')
            ->with('sku', static::PRODUCT_ABSTRACT_SKU, 'en_US')
            ->willReturn($productData);

        return $productStorageClientMock;
    }

    /**
     * The search client stub serves the given document and reports a token match for every element
     * containing the token as a substring — a stand-in for the real index-time analysis that keeps the
     * focus of these tests on the resolver's attribution logic. `getTextTokenOffsetsForTexts()` is the
     * ONE batched call the resolver now makes (see `warmTokenOffsetsCache()`); the callback itself
     * de-duplicates, mirroring what the real client would do.
     *
     * @param array<string, array<int, string>> $documentData
     */
    protected function createSearchElasticsearchClientMock(array $documentData): SearchDebugClientInterface
    {
        $searchDebugClientMock = $this->createMock(SearchDebugClientInterface::class);
        $searchDebugClientMock->method('findPageDocumentData')
            ->with('product_abstract', '123', 'en_US')
            ->willReturn($documentData);

        $searchDebugClientMock->method('getTextTokenOffsetsForTexts')
            ->willReturnCallback(
                function (array $texts): array {
                    $tokenOffsetsByText = [];
                    foreach (array_unique($texts) as $text) {
                        $tokenOffsetsByText[$text] = str_contains(mb_strtolower($text), static::TOKEN)
                            ? [['token' => static::TOKEN, 'startOffset' => 0, 'endOffset' => 5]]
                            : [];
                    }

                    return $tokenOffsetsByText;
                },
            );

        return $searchDebugClientMock;
    }

    /**
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface $productStorageClient
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     * @param \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface|null $productCategoryStorageClient
     * @param \Spryker\Client\CategoryStorage\CategoryStorageClientInterface|null $categoryStorageClient
     * @param \Spryker\Client\MerchantStorage\MerchantStorageClientInterface|null $merchantStorageClient
     */
    protected function createResolver(
        ProductStorageClientInterface $productStorageClient,
        SearchDebugClientInterface $searchDebugClient,
        ?ProductCategoryStorageClientInterface $productCategoryStorageClient = null,
        ?CategoryStorageClientInterface $categoryStorageClient = null,
        ?MerchantStorageClientInterface $merchantStorageClient = null,
    ): TokenSourceResolver {
        $storeClientMock = $this->createMock(StoreClientInterface::class);
        $storeClientMock->method('getCurrentStore')->willReturn((new StoreTransfer())->setName('DE'));

        if ($productCategoryStorageClient === null) {
            $productCategoryStorageClient = $this->createMock(ProductCategoryStorageClientInterface::class);
            $productCategoryStorageClient->method('findBulkProductAbstractCategory')->willReturn([]);
        }

        $tokenHighlighterMock = $this->createMock(TokenHighlighterInterface::class);
        $tokenHighlighterMock->method('highlight')->willReturnCallback(
            fn (string $text, array $matches): string => ($matches !== [] ? 'HL[' : 'TXT[') . $text . ']',
        );
        // None of these fixtures' matches ever overlap, so passthrough mirrors the real
        // TokenHighlighter::filterRenderable() for every case exercised here.
        $tokenHighlighterMock->method('filterRenderable')->willReturnArgument(0);

        $productSourceMapBuilder = new ProductSourceMapBuilder(
            $productStorageClient,
            $productCategoryStorageClient,
            $categoryStorageClient ?? $this->createMock(CategoryStorageClientInterface::class),
            $merchantStorageClient ?? $this->createMock(MerchantStorageClientInterface::class),
            $storeClientMock,
            new CategoryAncestorNameCollector(),
        );

        return new TokenSourceResolver(
            $productStorageClient,
            $searchDebugClient,
            $tokenHighlighterMock,
            $productSourceMapBuilder,
        );
    }
}
