<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\MerchantStorageCriteriaTransfer;
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;

/**
 * Builds the two lookup maps {@see TokenSourceResolver} labels a document's real indexed elements with:
 * which NAMED product source(s) a raw value came from (title, SKU, description, category, merchant name,
 * concrete variants — see {@see SOURCE_DEFINITIONS}), and which of the product's own searchable
 * attributes a value matches when no named source claims it.
 *
 * A separate concern from {@see TokenSourceResolver} on purpose: this class only GATHERS what a value
 * means, from Zed/Storage data; it never looks at the live search document or the searched token at all.
 * {@see TokenSourceResolver} is the other half — matching a document's REAL elements against these maps.
 */
class ProductSourceMapBuilder
{
    /**
     * @var string
     */
    protected const STORAGE_KEY_DESCRIPTION = 'description';

    /**
     * @var string
     */
    protected const STORAGE_KEY_MERCHANT_REFERENCE = 'merchant_reference';

    /**
     * @var string
     */
    protected const STORAGE_KEY_ATTRIBUTE_MAP = 'attribute_map';

    /**
     * Both abstract and concrete storage documents carry a flat `attributes` map (machine attribute key =>
     * localized value, e.g. `{"brand": "Example Company", "farbe": "Rot"}`) — confirmed live against a real
     * shop's own product storage data. Already fetched here (no extra client call, no Zed round trip
     * needed) via the same `findProductAbstractStorageDataByMapping()`/`getBulkProductConcreteStorageData()`
     * calls used for name/sku/description.
     *
     * @var string
     */
    protected const STORAGE_KEY_ATTRIBUTES = 'attributes';

    /**
     * @var string
     */
    protected const STORAGE_KEY_PRODUCT_CONCRETE_IDS = 'product_concrete_ids';

    /**
     * Source keys — one per known contributor of Spryker's two BASE full-text fields. A project can
     * register more fields than these two (see {@see SOURCE_DEFINITIONS} below) — this constant list only
     * names the contributors this package identifies out of the box.
     *
     * @var string
     */
    protected const KEY_TITLE = 'title';

    /**
     * @var string
     */
    protected const KEY_SKU = 'sku';

    /**
     * @var string
     */
    protected const KEY_CONCRETE_NAMES = 'concreteNames';

    /**
     * @var string
     */
    protected const KEY_CONCRETE_SKUS = 'concreteSkus';

    /**
     * @var string
     */
    protected const KEY_ABSTRACT_DESCRIPTION = 'abstractDescription';

    /**
     * @var string
     */
    protected const KEY_CONCRETE_DESCRIPTIONS = 'concreteDescriptions';

    /**
     * @var string
     */
    protected const KEY_DIRECT_CATEGORIES = 'directCategories';

    /**
     * @var string
     */
    protected const KEY_INDIRECT_CATEGORIES = 'indirectCategories';

    /**
     * @var string
     */
    protected const KEY_MERCHANT_NAME = 'merchantName';

    /**
     * One definition per known NAMED source value feeding the full-text fields: display label and the
     * ES-level field ("tier") the indexing pipeline writes it into.
     *
     * This tier assignment mirrors a basic shop's default `ProductPageSearchDependencyProvider` wiring
     * (name/sku/direct-category/merchant-name → boosted, everything else → unboosted) — it is PHP plugin
     * registration code (`getProductAbstractMapExpanderPlugins()`), not something the cluster or Zed
     * exposes generically, so a project registering a different set of map-expander plugins (or moving a
     * field between tiers) needs to edit this list to match its own wiring; nothing here can discover
     * that automatically. See the package README's "Limitations" section.
     *
     * Unlike earlier revisions of this class, this list no longer decides WHAT gets checked — the real
     * indexed document does (see {@see TokenSourceResolver}). It only assigns readable labels to document
     * elements it can identify; an element no entry claims is checked against the product's own attribute
     * values next (see {@see collectAttributeLabelsByValue()}), and only falls back to the generic "other
     * indexed value" label if neither identifies it — so a new contributor on the indexing side degrades
     * gracefully rather than disappearing.
     *
     * Public: also referenced by {@see TokenSourceResolver}, which uses it for display labels and
     * canonical row ordering.
     *
     * @var array<string, array{labelKey: string, tier: string}>
     */
    public const SOURCE_DEFINITIONS = [
        self::KEY_TITLE => [
            'labelKey' => 'search_debug.token_source.field.title',
            'tier' => PageIndexMap::FULL_TEXT_BOOSTED,
        ],
        self::KEY_SKU => [
            'labelKey' => 'search_debug.token_source.field.sku',
            'tier' => PageIndexMap::FULL_TEXT_BOOSTED,
        ],
        self::KEY_CONCRETE_NAMES => [
            'labelKey' => 'search_debug.token_source.field.concrete_names',
            'tier' => PageIndexMap::FULL_TEXT,
        ],
        self::KEY_CONCRETE_SKUS => [
            'labelKey' => 'search_debug.token_source.field.concrete_skus',
            'tier' => PageIndexMap::FULL_TEXT,
        ],
        self::KEY_ABSTRACT_DESCRIPTION => [
            'labelKey' => 'search_debug.token_source.field.abstract_description',
            'tier' => PageIndexMap::FULL_TEXT,
        ],
        self::KEY_CONCRETE_DESCRIPTIONS => [
            'labelKey' => 'search_debug.token_source.field.concrete_descriptions',
            'tier' => PageIndexMap::FULL_TEXT,
        ],
        self::KEY_DIRECT_CATEGORIES => [
            'labelKey' => 'search_debug.token_source.field.direct_categories',
            'tier' => PageIndexMap::FULL_TEXT_BOOSTED,
        ],
        self::KEY_INDIRECT_CATEGORIES => [
            'labelKey' => 'search_debug.token_source.field.indirect_categories',
            'tier' => PageIndexMap::FULL_TEXT,
        ],
        self::KEY_MERCHANT_NAME => [
            'labelKey' => 'search_debug.token_source.field.merchant_name',
            'tier' => PageIndexMap::FULL_TEXT_BOOSTED,
        ],
    ];

    /**
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface $productStorageClient
     * @param \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface $productCategoryStorageClient
     * @param \Spryker\Client\CategoryStorage\CategoryStorageClientInterface $categoryStorageClient
     * @param \Spryker\Client\MerchantStorage\MerchantStorageClientInterface|null $merchantStorageClient
     *   Null on shops without `spryker/merchant-storage` (non-Marketplace) — merchant-name attribution is
     *   then skipped rather than the whole tool being unavailable.
     * @param \Spryker\Client\Store\StoreClientInterface $storeClient
     * @param \SprykerCommunity\Yves\SearchDebugWidget\Resolver\CategoryAncestorNameCollector $categoryAncestorNameCollector
     * @param array<\SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin\TokenSourceProviderPluginInterface> $tokenSourceProviderPlugins
     */
    public function __construct(
        protected ProductStorageClientInterface $productStorageClient,
        protected ProductCategoryStorageClientInterface $productCategoryStorageClient,
        protected CategoryStorageClientInterface $categoryStorageClient,
        /**
         * Null on any shop without `spryker/merchant-storage` installed — merchant names are a Marketplace-only
         * concept, so a plain B2B/B2C shop has no such module and must not be forced to install one (with its
         * Propel tables and publish/sync infrastructure) just to use this debug tool. Every merchant lookup is
         * guarded; a shop without it simply gets no merchant-name attribution, which is exactly right because
         * there are no merchants to attribute to. See {@see findMerchantName()}.
         */
        protected ?MerchantStorageClientInterface $merchantStorageClient,
        protected StoreClientInterface $storeClient,
        protected CategoryAncestorNameCollector $categoryAncestorNameCollector,
        protected array $tokenSourceProviderPlugins = [],
    ) {
    }

    /**
     * @param array<string, mixed> $productData
     * @param string $localeName
     *
     * @return array{sourceKeysByValue: array<string, array<string, array<int, string>>>, attributeLabelByValue: array<string, array<int, string>>}
     */
    public function build(array $productData, string $localeName): array
    {
        // Fetched once, here, and passed into every method below — they used to each independently fetch
        // the same bulk concrete-storage rows for the same product/locale, doubling this request's
        // storage round trips for identical data.
        $concreteStorageData = $this->fetchConcreteStorageData($productData, $localeName);

        $attributeLabelByValue = $this->collectAttributeLabelsByValue($productData, $concreteStorageData);
        $attributeLabelByValue = $this->applyTokenSourceProviderPlugins($attributeLabelByValue, $productData, $concreteStorageData, $localeName);

        return [
            'sourceKeysByValue' => $this->collectSourceKeysByValue($productData, $localeName, $concreteStorageData),
            'attributeLabelByValue' => $attributeLabelByValue,
        ];
    }

    /**
     * @param array<string, mixed> $productData
     * @param string $localeName
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchConcreteStorageData(array $productData, string $localeName): array
    {
        $productConcreteIds = $productData[static::STORAGE_KEY_ATTRIBUTE_MAP][static::STORAGE_KEY_PRODUCT_CONCRETE_IDS] ?? [];

        if (!$productConcreteIds) {
            return [];
        }

        return $this->productStorageClient->getBulkProductConcreteStorageData(
            array_values(array_map(static fn ($value): int => (int)$value, (array)$productConcreteIds)),
            $localeName,
        );
    }

    /**
     * Builds the per-tier lookup of raw source value => source keys used to label document elements.
     * The indexing pipeline writes each source value into its tier verbatim, so plain string equality
     * identifies an element's origin — but the SAME string can legitimately be contributed by two
     * DIFFERENT sources (e.g. a merchant name that happens to equal the product title): once merged into
     * one document element, those are genuinely indistinguishable, not a case this can resolve with more
     * cleverness. Rather than silently keeping one source and dropping the other (the earlier behavior),
     * every colliding source key is kept, in canonical definition order — {@see TokenSourceResolver::buildTierRows()}
     * renders a value with multiple source keys as one honestly-ambiguous row instead of guessing.
     *
     * @param array<string, mixed> $productData
     * @param string $localeName
     * @param array<int, array<string, mixed>> $concreteStorageData Already-fetched concrete storage rows
     *   for this product (see {@see build()}) — reused here instead of re-fetching the same bulk data a
     *   second time within one request.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    protected function collectSourceKeysByValue(array $productData, string $localeName, array $concreteStorageData): array
    {
        $storeName = $this->storeClient->getCurrentStore()->getNameOrFail();

        $valuesBySourceKey = [
            static::KEY_TITLE => [(string)($productData[TokenSourceResolver::STORAGE_KEY_NAME] ?? '')],
            static::KEY_SKU => [(string)($productData[TokenSourceResolver::STORAGE_KEY_SKU] ?? '')],
            static::KEY_ABSTRACT_DESCRIPTION => [(string)($productData[static::STORAGE_KEY_DESCRIPTION] ?? '')],
            static::KEY_MERCHANT_NAME => [$this->findMerchantName($productData)],
        ];

        $valuesBySourceKey += $this->collectConcreteValues($concreteStorageData);
        $valuesBySourceKey += $this->collectCategoryValues($productData, $localeName, $storeName);

        $sourceKeysByValue = [];
        foreach (static::SOURCE_DEFINITIONS as $sourceKey => $definition) {
            foreach ($valuesBySourceKey[$sourceKey] ?? [] as $value) {
                if ($value === '') {
                    continue;
                }

                $existingSourceKeys = $sourceKeysByValue[$definition['tier']][$value] ?? [];
                if (!in_array($sourceKey, $existingSourceKeys, true)) {
                    $existingSourceKeys[] = $sourceKey;
                }

                $sourceKeysByValue[$definition['tier']][$value] = $existingSourceKeys;
            }
        }

        return $sourceKeysByValue;
    }

    /**
     * All concrete variants contribute to the same three source keys — the indexing side flattens every
     * concrete's name/sku/description into the shared `full-text` array, one element per value.
     *
     * @param array<int, array<string, mixed>> $concreteStorageData Already-fetched concrete storage rows
     *   for this product (see {@see build()}) — reused here instead of re-fetching the same bulk data a
     *   second time within one request.
     *
     * @return array<string, array<int, string>>
     */
    protected function collectConcreteValues(array $concreteStorageData): array
    {
        if ($concreteStorageData === []) {
            return [];
        }

        $concreteValues = [
            static::KEY_CONCRETE_NAMES => [],
            static::KEY_CONCRETE_SKUS => [],
            static::KEY_CONCRETE_DESCRIPTIONS => [],
        ];

        foreach ($concreteStorageData as $concreteData) {
            $concreteValues[static::KEY_CONCRETE_NAMES][] = (string)($concreteData[TokenSourceResolver::STORAGE_KEY_NAME] ?? '');
            $concreteValues[static::KEY_CONCRETE_SKUS][] = (string)($concreteData[TokenSourceResolver::STORAGE_KEY_SKU] ?? '');
            $concreteValues[static::KEY_CONCRETE_DESCRIPTIONS][] = (string)($concreteData[static::STORAGE_KEY_DESCRIPTION] ?? '');
        }

        return $concreteValues;
    }

    /**
     * Labels document elements no NAMED source claims (see {@see SOURCE_DEFINITIONS}) by matching them
     * against the product's own searchable attribute values — both abstract- and concrete-level
     * `attributes`, a flat machine-key => localized-value map already present on both storage documents.
     * This means a shop's Search Preferences attributes (Zed > Catalog > Manage Attributes) show up under
     * their real attribute key instead of the generic "other indexed value" label — with zero Zed calls,
     * since this data is already reachable via the same Yves storage clients this class already uses.
     *
     * A value shared by two different attributes now keeps EVERY colliding attribute key, same as
     * {@see collectSourceKeysByValue()}'s NAMED sources — {@see TokenSourceResolver::buildTierRows()}
     * renders a value with multiple attribute keys as one honestly-ambiguous row instead of picking one.
     *
     * @param array<string, mixed> $productData
     * @param array<int, array<string, mixed>> $concreteStorageData Already-fetched concrete storage rows
     *   for this product (see {@see build()}) — reused here instead of re-fetching the same bulk data a
     *   second time within one request.
     *
     * @return array<string, array<int, string>>
     */
    protected function collectAttributeLabelsByValue(array $productData, array $concreteStorageData): array
    {
        $attributeKeysByValue = [];

        foreach ((array)($productData[static::STORAGE_KEY_ATTRIBUTES] ?? []) as $attributeKey => $value) {
            $this->addAttributeLabel($attributeKeysByValue, (string)$attributeKey, $value);
        }

        foreach ($concreteStorageData as $concreteData) {
            foreach ((array)($concreteData[static::STORAGE_KEY_ATTRIBUTES] ?? []) as $attributeKey => $value) {
                $this->addAttributeLabel($attributeKeysByValue, (string)$attributeKey, $value);
            }
        }

        return $attributeKeysByValue;
    }

    /**
     * @param array<string, array<int, string>> $attributeKeysByValue
     * @param string $attributeKey
     * @param mixed $value
     */
    protected function addAttributeLabel(array &$attributeKeysByValue, string $attributeKey, $value): void
    {
        if (!is_scalar($value)) {
            return;
        }

        $value = (string)$value;

        if ($value === '') {
            return;
        }

        if (in_array($attributeKey, $attributeKeysByValue[$value] ?? [], true)) {
            return;
        }

        $attributeKeysByValue[$value][] = $attributeKey;
    }

    /**
     * @param array<string, mixed> $productData
     * @param string $localeName
     * @param string $storeName
     *
     * @return array<string, array<int, string>>
     */
    protected function collectCategoryValues(array $productData, string $localeName, string $storeName): array
    {
        $productAbstractCategoryStorageTransfers = $this->productCategoryStorageClient
            ->findBulkProductAbstractCategory([(int)$productData['id_product_abstract']], $localeName, $storeName);

        $productAbstractCategoryStorageTransfer = $productAbstractCategoryStorageTransfers[0] ?? null;

        if ($productAbstractCategoryStorageTransfer === null) {
            return [];
        }

        $directNames = [];
        $nodeIds = [];
        foreach ($productAbstractCategoryStorageTransfer->getCategories() as $productCategoryStorageTransfer) {
            $directNames[] = (string)$productCategoryStorageTransfer->getName();
            $nodeIds[] = (int)$productCategoryStorageTransfer->getCategoryNodeId();
        }

        return [
            static::KEY_DIRECT_CATEGORIES => $directNames,
            static::KEY_INDIRECT_CATEGORIES => $this->collectAncestorCategoryNames($nodeIds, $localeName, $storeName),
        ];
    }

    /**
     * @param array<int> $directCategoryNodeIds
     * @param string $localeName
     * @param string $storeName
     *
     * @return array<int, string>
     */
    protected function collectAncestorCategoryNames(array $directCategoryNodeIds, string $localeName, string $storeName): array
    {
        if ($directCategoryNodeIds === []) {
            return [];
        }

        $categoryNodeStorageTransfers = $this->categoryStorageClient
            ->getCategoryNodeByIds($directCategoryNodeIds, $localeName, $storeName);

        return $this->categoryAncestorNameCollector->collect($categoryNodeStorageTransfers);
    }

    /**
     * Project-registered plugins name values this package cannot possibly know about — anything a
     * project's own `ProductPageSearch` map expanders contribute. Their labels take precedence over the
     * generic attribute-key fallback for the same value: a plugin saying "this is the technical
     * datasheet title" is strictly more informative than the raw attribute key it happens to live under.
     *
     * @param array<string, array<int, string>> $attributeLabelByValue
     * @param array<string, mixed> $productData
     * @param array<int, array<string, mixed>> $concreteStorageData
     * @param string $localeName
     *
     * @return array<string, array<int, string>>
     */
    protected function applyTokenSourceProviderPlugins(
        array $attributeLabelByValue,
        array $productData,
        array $concreteStorageData,
        string $localeName,
    ): array {
        foreach ($this->tokenSourceProviderPlugins as $tokenSourceProviderPlugin) {
            $labelsByValue = $tokenSourceProviderPlugin->getLabelsByValue($productData, $concreteStorageData, $localeName);

            foreach ($labelsByValue as $value => $labels) {
                $value = (string)$value;

                if ($value === '' || $labels === []) {
                    continue;
                }

                $attributeLabelByValue[$value] = array_values(array_unique(
                    array_merge($attributeLabelByValue[$value] ?? [], array_map(static fn ($label): string => (string)$label, $labels)),
                ));
            }
        }

        return $attributeLabelByValue;
    }

    /**
     * @param array<string, mixed> $productData
     */
    protected function findMerchantName(array $productData): string
    {
        if ($this->merchantStorageClient === null) {
            return '';
        }

        $merchantReference = (string)($productData[static::STORAGE_KEY_MERCHANT_REFERENCE] ?? '');

        if ($merchantReference === '') {
            return '';
        }

        $merchantStorageCriteriaTransfer = (new MerchantStorageCriteriaTransfer())
            ->addMerchantReference($merchantReference);

        $merchantStorageTransfer = $this->merchantStorageClient->findOne($merchantStorageCriteriaTransfer);

        return $merchantStorageTransfer !== null ? (string)$merchantStorageTransfer->getName() : '';
    }
}
