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
use SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin\TokenSourceProviderPluginInterface;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\CategoryAncestorNameCollector;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\ProductSourceMapBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group ProductSourceMapBuilderTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Yves\SearchDebugWidget\SearchDebugWidgetTester $tester
 */
class ProductSourceMapBuilderTest extends Unit
{
    /**
     * @return void
     */
    public function testBuildAttributesTitleSkuAndDescriptionToTheirDeclaredTiers(): void
    {
        // Arrange
        $builder = $this->createBuilder();

        // Act
        $result = $builder->build([
            'id_product_abstract' => 123,
            'name' => 'Steel Cable',
            'sku' => 'STEEL-1',
            'description' => 'A cable for outdoor use',
        ], 'en_US');

        // Assert
        $this->assertSame(['title'], $result['sourceKeysByValue']['full-text-boosted']['Steel Cable']);
        $this->assertSame(['sku'], $result['sourceKeysByValue']['full-text-boosted']['STEEL-1']);
        $this->assertSame(['abstractDescription'], $result['sourceKeysByValue']['full-text']['A cable for outdoor use']);
    }

    /**
     * A value contributed by two DIFFERENT named sources (e.g. a merchant named the same as the product)
     * is genuinely ambiguous once merged — both source keys must be kept, in canonical definition order,
     * rather than silently picking one.
     *
     * @return void
     */
    public function testBuildKeepsEveryCollidingSourceKeyForAnIdenticalValue(): void
    {
        // Arrange
        $merchantStorageClientMock = $this->createMock(MerchantStorageClientInterface::class);
        $merchantStorageClientMock->method('findOne')->willReturn((new MerchantStorageTransfer())->setName('Acme'));

        $builder = $this->createBuilder(null, null, $merchantStorageClientMock);

        // Act
        $result = $builder->build([
            'id_product_abstract' => 123,
            'name' => 'Acme',
            'sku' => 'STEEL-1',
            'merchant_reference' => 'MER000005',
        ], 'en_US');

        // Assert
        $this->assertSame(['title', 'merchantName'], $result['sourceKeysByValue']['full-text-boosted']['Acme']);
    }

    /**
     * Concrete variant values (name/sku/description) all flatten into the shared full-text source keys,
     * one element per variant.
     *
     * @return void
     */
    public function testBuildCollectsConcreteVariantValues(): void
    {
        // Arrange
        $productStorageClientMock = $this->createMock(ProductStorageClientInterface::class);
        $productStorageClientMock->method('getBulkProductConcreteStorageData')
            ->with([11, 12], 'en_US')
            ->willReturn([
                ['name' => 'Cable A', 'sku' => 'CONCRETE-1', 'description' => 'Red variant'],
                ['name' => 'Cable B', 'sku' => 'CONCRETE-2', 'description' => ''],
            ]);

        $builder = $this->createBuilder(null, null, null, $productStorageClientMock);

        // Act
        $result = $builder->build([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'attribute_map' => ['product_concrete_ids' => ['CONCRETE-1' => 11, 'CONCRETE-2' => 12]],
        ], 'en_US');

        // Assert
        $fullTextSources = $result['sourceKeysByValue']['full-text'];
        $this->assertSame(['concreteNames'], $fullTextSources['Cable A']);
        $this->assertSame(['concreteNames'], $fullTextSources['Cable B']);
        $this->assertSame(['concreteSkus'], $fullTextSources['CONCRETE-1']);
        $this->assertSame(['concreteDescriptions'], $fullTextSources['Red variant']);
        // An empty concrete description contributes nothing — never a blank source-value entry.
        $this->assertArrayNotHasKey('', $fullTextSources);
    }

    /**
     * Direct categories go to the boosted tier, indirect (ancestor) categories to the plain tier — the
     * ancestor walk is delegated to a real {@see CategoryAncestorNameCollector}.
     *
     * @return void
     */
    public function testBuildCollectsDirectAndIndirectCategoryValues(): void
    {
        // Arrange
        $directCategory = (new ProductCategoryStorageTransfer())->setName('Cables')->setCategoryNodeId(5);
        $productCategoryStorageClientMock = $this->createMock(ProductCategoryStorageClientInterface::class);
        $productCategoryStorageClientMock->method('findBulkProductAbstractCategory')
            ->willReturn([(new ProductAbstractCategoryStorageTransfer())->addCategory($directCategory)]);

        $parent = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('Electrical');
        $directNode = (new CategoryNodeStorageTransfer())->setNodeId(5)->setName('Cables')->addParents($parent);
        $categoryStorageClientMock = $this->createMock(CategoryStorageClientInterface::class);
        $categoryStorageClientMock->method('getCategoryNodeByIds')->willReturn([5 => $directNode]);

        $builder = $this->createBuilder($productCategoryStorageClientMock, $categoryStorageClientMock);

        // Act
        $result = $builder->build(['id_product_abstract' => 123, 'name' => 'Cable', 'sku' => 'ABSTRACT-1'], 'en_US');

        // Assert
        $this->assertSame(['directCategories'], $result['sourceKeysByValue']['full-text-boosted']['Cables']);
        $this->assertSame(['indirectCategories'], $result['sourceKeysByValue']['full-text']['Electrical']);
    }

    /**
     * @return void
     */
    public function testBuildReturnsAnEmptyMerchantNameWhenNoMerchantStorageClientIsConfigured(): void
    {
        // Arrange — null merchantStorageClient, as on a non-Marketplace shop.
        $builder = $this->createBuilder();

        // Act
        $result = $builder->build([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'merchant_reference' => 'MER000005',
        ], 'en_US');

        // Assert — no "merchantName" source key appears anywhere.
        $allSourceKeys = array_merge(...array_values(array_map('array_values', $result['sourceKeysByValue'])));
        $this->assertNotContains(['merchantName'], $allSourceKeys);
    }

    /**
     * Both abstract- and concrete-level `attributes` feed the label map used for elements no NAMED source
     * claims.
     *
     * @return void
     */
    public function testBuildCollectsAttributeLabelsFromAbstractAndConcreteLevels(): void
    {
        // Arrange
        $productStorageClientMock = $this->createMock(ProductStorageClientInterface::class);
        $productStorageClientMock->method('getBulkProductConcreteStorageData')
            ->willReturn([
                ['name' => 'Cable Red', 'sku' => 'CONCRETE-1', 'description' => '', 'attributes' => ['color' => 'Red']],
            ]);

        $builder = $this->createBuilder(null, null, null, $productStorageClientMock);

        // Act
        $result = $builder->build([
            'id_product_abstract' => 123,
            'name' => 'Cable',
            'sku' => 'ABSTRACT-1',
            'attributes' => ['brand' => 'Acme'],
            'attribute_map' => ['product_concrete_ids' => ['CONCRETE-1' => 11]],
        ], 'en_US');

        // Assert
        $this->assertSame(['brand'], $result['attributeLabelByValue']['Acme']);
        $this->assertSame(['color'], $result['attributeLabelByValue']['Red']);
    }

    /**
     * A registered {@see TokenSourceProviderPluginInterface} contributes additional labels, merged in
     * (not replacing) whatever the built-in attribute-label collection already found.
     *
     * @return void
     */
    public function testBuildMergesInLabelsFromRegisteredTokenSourceProviderPlugins(): void
    {
        // Arrange
        $pluginMock = $this->createMock(TokenSourceProviderPluginInterface::class);
        $pluginMock->method('getLabelsByValue')->willReturn([
            'Datasheet Title' => ['acme.search_debug.source.datasheet_title'],
        ]);

        $builder = $this->createBuilder(null, null, null, null, [$pluginMock]);

        // Act
        $result = $builder->build(['id_product_abstract' => 123, 'name' => 'Cable', 'sku' => 'ABSTRACT-1'], 'en_US');

        // Assert
        $this->assertSame(['acme.search_debug.source.datasheet_title'], $result['attributeLabelByValue']['Datasheet Title']);
    }

    /**
     * @param \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface|null $productCategoryStorageClient
     * @param \Spryker\Client\CategoryStorage\CategoryStorageClientInterface|null $categoryStorageClient
     * @param \Spryker\Client\MerchantStorage\MerchantStorageClientInterface|null $merchantStorageClient
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface|null $productStorageClient
     * @param array<\SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin\TokenSourceProviderPluginInterface> $tokenSourceProviderPlugins
     *
     * @return \SprykerCommunity\Yves\SearchDebugWidget\Resolver\ProductSourceMapBuilder
     */
    protected function createBuilder(
        ?ProductCategoryStorageClientInterface $productCategoryStorageClient = null,
        ?CategoryStorageClientInterface $categoryStorageClient = null,
        ?MerchantStorageClientInterface $merchantStorageClient = null,
        ?ProductStorageClientInterface $productStorageClient = null,
        array $tokenSourceProviderPlugins = [],
    ): ProductSourceMapBuilder {
        if ($productCategoryStorageClient === null) {
            $productCategoryStorageClient = $this->createMock(ProductCategoryStorageClientInterface::class);
            $productCategoryStorageClient->method('findBulkProductAbstractCategory')->willReturn([]);
        }

        $storeClientMock = $this->createMock(StoreClientInterface::class);
        $storeClientMock->method('getCurrentStore')->willReturn((new StoreTransfer())->setName('DE'));

        return new ProductSourceMapBuilder(
            $productStorageClient ?? $this->createMock(ProductStorageClientInterface::class),
            $productCategoryStorageClient,
            $categoryStorageClient ?? $this->createMock(CategoryStorageClientInterface::class),
            $merchantStorageClient,
            $storeClientMock,
            new CategoryAncestorNameCollector(),
            $tokenSourceProviderPlugins,
        );
    }
}
