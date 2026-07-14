<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\CategoryNodeStorageTransfer;
use Generated\Shared\Transfer\MerchantStorageCriteriaTransfer;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;

class TokenSourceResolver implements TokenSourceResolverInterface
{
    /**
     * The search resource whose documents this resolver reads — see
     * `vendor/spryker/product-page-search` (`spy_product_abstract_page_search` synchronization behavior).
     *
     * @var string
     */
    protected const RESOURCE_NAME_PRODUCT_ABSTRACT = 'product_abstract';

    /**
     * Hard cap on category-ancestor recursion depth — a defensive guard against unexpected storage data
     * (its real job is terminating a hypothetical chain of nodes without ids, which the visited-set
     * below cannot register), not an expected real-world tree depth.
     *
     * @var int
     */
    protected const MAX_CATEGORY_ANCESTOR_DEPTH = 20;

    /**
     * The abstract product ID never appears in the URL — only the SKU does, so this internal lookup type
     * is how the SKU from the query string gets turned back into the ID the rest of this class works with.
     *
     * @var string
     */
    protected const MAPPING_TYPE_SKU = 'sku';

    /**
     * Storage-document keys of the raw product data this resolver labels elements with.
     *
     * @var string
     */
    protected const STORAGE_KEY_NAME = 'name';

    /**
     * @var string
     */
    protected const STORAGE_KEY_SKU = 'sku';

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
     * @var string
     */
    protected const STORAGE_KEY_PRODUCT_CONCRETE_IDS = 'product_concrete_ids';

    /**
     * Source keys — one per known contributor of the two full-text fields.
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
     * Label for document elements no known source claims — e.g. searchable product attributes
     * (Zed > Search Preferences, `spy_product_search_attribute_map`) or any custom map-expander plugin.
     *
     * @var string
     */
    protected const LABEL_KEY_OTHER = 'search_debug.token_source.field.other';

    /**
     * One definition per known source value feeding the two full-text fields: display label and the
     * ES-level field ("tier") the indexing pipeline writes it into — mirrors
     * `ProductAbstractSearchDataMapper::buildPageMap()` (name/sku → boosted) and the category/merchant
     * expanders (direct category + merchant name → boosted, everything else → unboosted).
     *
     * Unlike earlier revisions of this class, this list no longer decides WHAT gets checked — the real
     * indexed document does. It only assigns readable labels to document elements it can identify; an
     * element no entry claims still shows up, under the {@link LABEL_KEY_OTHER} label, so a new
     * contributor on the indexing side degrades to a generic label rather than disappearing.
     *
     * @var array<string, array{labelKey: string, tier: string}>
     */
    protected const SOURCE_DEFINITIONS = [
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
     * Tier display order: boosted first, matching the order the SRP's own token badges read in.
     *
     * @var array<string, string>
     */
    protected const TIER_LABEL_KEYS = [
        PageIndexMap::FULL_TEXT_BOOSTED => 'search_debug.token_source.tier.full_text_boosted',
        PageIndexMap::FULL_TEXT => 'search_debug.token_source.tier.full_text',
    ];

    /**
     * @var \Spryker\Client\ProductStorage\ProductStorageClientInterface
     */
    protected ProductStorageClientInterface $productStorageClient;

    /**
     * @var \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface
     */
    protected ProductCategoryStorageClientInterface $productCategoryStorageClient;

    /**
     * @var \Spryker\Client\CategoryStorage\CategoryStorageClientInterface
     */
    protected CategoryStorageClientInterface $categoryStorageClient;

    /**
     * @var \Spryker\Client\MerchantStorage\MerchantStorageClientInterface
     */
    protected MerchantStorageClientInterface $merchantStorageClient;

    /**
     * @var \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface
     */
    protected SearchDebugClientInterface $searchDebugClient;

    /**
     * @var \Spryker\Client\Store\StoreClientInterface
     */
    protected StoreClientInterface $storeClient;

    /**
     * @var \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface
     */
    protected TokenHighlighterInterface $tokenHighlighter;

    /**
     * The real query-time boost applied to `full-text-boosted` — see
     * `SearchDebugWidgetConfig::getFullTextBoostedBoostingValue()`. Shown next to the tier heading so
     * the debug page doesn't just say *which* field a token matched, but how much that match was worth
     * relative to `full-text`, which the same query leaves at ES's implicit boost of 1 — no config
     * constant exists for that value since it's simply the absence of a `^` suffix, not a setting.
     *
     * @var int
     */
    protected int $fullTextBoostedBoostingValue;

    /**
     * Memoizes `getTextTokenOffsets()` results per distinct element string — identical values recur in
     * one document (e.g. a concrete name equal to the abstract name) and need only one `_analyze` call.
     *
     * @var array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    protected array $tokenOffsetsCache = [];

    /**
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface $productStorageClient
     * @param \Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface $productCategoryStorageClient
     * @param \Spryker\Client\CategoryStorage\CategoryStorageClientInterface $categoryStorageClient
     * @param \Spryker\Client\MerchantStorage\MerchantStorageClientInterface $merchantStorageClient
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     * @param \Spryker\Client\Store\StoreClientInterface $storeClient
     * @param \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface $tokenHighlighter
     * @param int $fullTextBoostedBoostingValue
     */
    public function __construct(
        ProductStorageClientInterface $productStorageClient,
        ProductCategoryStorageClientInterface $productCategoryStorageClient,
        CategoryStorageClientInterface $categoryStorageClient,
        MerchantStorageClientInterface $merchantStorageClient,
        SearchDebugClientInterface $searchDebugClient,
        StoreClientInterface $storeClient,
        TokenHighlighterInterface $tokenHighlighter,
        int $fullTextBoostedBoostingValue
    ) {
        $this->productStorageClient = $productStorageClient;
        $this->productCategoryStorageClient = $productCategoryStorageClient;
        $this->categoryStorageClient = $categoryStorageClient;
        $this->merchantStorageClient = $merchantStorageClient;
        $this->searchDebugClient = $searchDebugClient;
        $this->storeClient = $storeClient;
        $this->tokenHighlighter = $tokenHighlighter;
        $this->fullTextBoostedBoostingValue = $fullTextBoostedBoostingValue;
    }

    /**
     * @param string $productAbstractSku
     * @param string $token
     * @param string $localeName
     *
     * @return array{
     *     productTitle: string,
     *     productSku: string,
     *     tiers: array<int, array{
     *         key: string,
     *         labelKey: string,
     *         boost: int,
     *         rows: array<int, array{labelKey: string, matched: bool, highlightedHtml: string|null}>,
     *     }>,
     * }|null
     */
    public function resolve(string $productAbstractSku, string $token, string $localeName): ?array
    {
        $productData = $this->productStorageClient->findProductAbstractStorageDataByMapping(
            static::MAPPING_TYPE_SKU,
            $productAbstractSku,
            $localeName,
        );

        if ($productData === null) {
            return null;
        }

        $documentData = $this->searchDebugClient->findPageDocumentData(
            static::RESOURCE_NAME_PRODUCT_ABSTRACT,
            (string)$productData['id_product_abstract'],
            $localeName,
        );

        $sourceKeysByValue = $this->collectSourceKeysByValue($productData, $localeName);

        return [
            'productTitle' => (string)($productData[static::STORAGE_KEY_NAME] ?? ''),
            'productSku' => (string)($productData[static::STORAGE_KEY_SKU] ?? ''),
            'tiers' => $this->buildTiers($documentData ?? [], $sourceKeysByValue, $token),
        ];
    }

    /**
     * Attributes each tier's real document elements: analyzes every element with the index-time
     * analyzer, marks the ones containing the token, and labels each element via the per-tier
     * value-to-source lookup — falling back to the generic "other indexed value" label for elements no
     * known source claims (searchable attributes, custom expanders).
     *
     * @param array<string, mixed> $documentData
     * @param array<string, array<string, string>> $sourceKeysByValue
     * @param string $token
     *
     * @return array<int, array{
     *     key: string,
     *     labelKey: string,
     *     boost: int,
     *     rows: array<int, array{labelKey: string, matched: bool, highlightedHtml: string|null}>,
     * }>
     */
    protected function buildTiers(array $documentData, array $sourceKeysByValue, string $token): array
    {
        $tierBoosts = [
            PageIndexMap::FULL_TEXT_BOOSTED => $this->fullTextBoostedBoostingValue,
            PageIndexMap::FULL_TEXT => 1,
        ];

        $tiers = [];
        foreach (static::TIER_LABEL_KEYS as $tier => $tierLabelKey) {
            $elements = array_map('strval', (array)($documentData[$tier] ?? []));

            $tiers[] = [
                'key' => $tier,
                'labelKey' => $tierLabelKey,
                'boost' => $tierBoosts[$tier],
                'rows' => $this->buildTierRows($elements, $sourceKeysByValue[$tier] ?? [], $token),
            ];
        }

        return $tiers;
    }

    /**
     * One row per identified source (in canonical definition order), showing the matched elements
     * highlighted or a compact "no match"; unidentified elements follow as one row each, ALWAYS showing
     * their text — for those, the value itself is the diagnostic information.
     *
     * @param array<int, string> $elements
     * @param array<string, string> $sourceKeyByValue
     * @param string $token
     *
     * @return array<int, array{labelKey: string, matched: bool, highlightedHtml: string|null}>
     */
    protected function buildTierRows(array $elements, array $sourceKeyByValue, string $token): array
    {
        $matchedHtmlBySourceKey = [];
        $presentSourceKeys = [];
        $otherRows = [];

        foreach ($elements as $element) {
            if (trim($element) === '') {
                continue;
            }

            $matches = $this->findTokenMatches($element, $token);
            $sourceKey = $sourceKeyByValue[$element] ?? null;

            if ($sourceKey === null) {
                $otherRows[] = [
                    'labelKey' => static::LABEL_KEY_OTHER,
                    'matched' => $matches !== [],
                    'highlightedHtml' => $this->tokenHighlighter->highlight($element, $matches),
                ];

                continue;
            }

            $presentSourceKeys[$sourceKey] = true;
            if ($matches !== []) {
                $matchedHtmlBySourceKey[$sourceKey][] = $this->tokenHighlighter->highlight($element, $matches);
            }
        }

        $rows = [];
        foreach (static::SOURCE_DEFINITIONS as $sourceKey => $definition) {
            if (!isset($presentSourceKeys[$sourceKey])) {
                continue;
            }

            $matchedHtml = $matchedHtmlBySourceKey[$sourceKey] ?? [];
            $rows[] = [
                'labelKey' => $definition['labelKey'],
                'matched' => $matchedHtml !== [],
                'highlightedHtml' => $matchedHtml !== [] ? implode("\n", $matchedHtml) : null,
            ];
        }

        return array_merge($rows, $otherRows);
    }

    /**
     * @param string $element
     * @param string $token
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    protected function findTokenMatches(string $element, string $token): array
    {
        if (!isset($this->tokenOffsetsCache[$element])) {
            $this->tokenOffsetsCache[$element] = $this->searchDebugClient->getTextTokenOffsets($element);
        }

        return array_values(array_filter(
            $this->tokenOffsetsCache[$element],
            fn (array $tokenOffset): bool => $tokenOffset['token'] === $token,
        ));
    }

    /**
     * Builds the per-tier lookup of raw source value => source key used to label document elements.
     * The indexing pipeline writes each source value into its tier verbatim, so plain string equality
     * identifies an element's origin; a value contributed by two sources in the SAME tier keeps the
     * first (canonical-order) source's label.
     *
     * @param array<string, mixed> $productData
     * @param string $localeName
     *
     * @return array<string, array<string, string>>
     */
    protected function collectSourceKeysByValue(array $productData, string $localeName): array
    {
        $storeName = $this->storeClient->getCurrentStore()->getName();

        $valuesBySourceKey = [
            static::KEY_TITLE => [(string)($productData[static::STORAGE_KEY_NAME] ?? '')],
            static::KEY_SKU => [(string)($productData[static::STORAGE_KEY_SKU] ?? '')],
            static::KEY_ABSTRACT_DESCRIPTION => [(string)($productData[static::STORAGE_KEY_DESCRIPTION] ?? '')],
            static::KEY_MERCHANT_NAME => [$this->findMerchantName($productData)],
        ];

        $valuesBySourceKey += $this->collectConcreteValues($productData, $localeName);
        $valuesBySourceKey += $this->collectCategoryValues($productData, $localeName, $storeName);

        $sourceKeysByValue = [];
        foreach (static::SOURCE_DEFINITIONS as $sourceKey => $definition) {
            foreach ($valuesBySourceKey[$sourceKey] ?? [] as $value) {
                if ($value === '') {
                    continue;
                }

                $sourceKeysByValue[$definition['tier']][$value] ??= $sourceKey;
            }
        }

        return $sourceKeysByValue;
    }

    /**
     * All concrete variants contribute to the same three source keys — the indexing side flattens every
     * concrete's name/sku/description into the shared `full-text` array, one element per value.
     *
     * @param array<string, mixed> $productData
     * @param string $localeName
     *
     * @return array<string, array<int, string>>
     */
    protected function collectConcreteValues(array $productData, string $localeName): array
    {
        $productConcreteIds = $productData[static::STORAGE_KEY_ATTRIBUTE_MAP][static::STORAGE_KEY_PRODUCT_CONCRETE_IDS] ?? [];

        if (!$productConcreteIds) {
            return [];
        }

        $concreteValues = [
            static::KEY_CONCRETE_NAMES => [],
            static::KEY_CONCRETE_SKUS => [],
            static::KEY_CONCRETE_DESCRIPTIONS => [],
        ];

        $concreteStorageData = $this->productStorageClient->getBulkProductConcreteStorageData(
            array_values(array_map('intval', (array)$productConcreteIds)),
            $localeName,
        );

        foreach ($concreteStorageData as $concreteData) {
            $concreteValues[static::KEY_CONCRETE_NAMES][] = (string)($concreteData[static::STORAGE_KEY_NAME] ?? '');
            $concreteValues[static::KEY_CONCRETE_SKUS][] = (string)($concreteData[static::STORAGE_KEY_SKU] ?? '');
            $concreteValues[static::KEY_CONCRETE_DESCRIPTIONS][] = (string)($concreteData[static::STORAGE_KEY_DESCRIPTION] ?? '');
        }

        return $concreteValues;
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
     * Recursively walks each direct category's ancestor chain. `CategoryNodeStorageTransfer::getParents()`
     * semantics (immediate parent only vs. already-flattened full chain) weren't conclusively confirmed
     * from static reading — recursing either way is safe: if the chain is already flat, recursion into
     * each terminal node's empty `getParents()` is simply a no-op.
     *
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

        $namesByNodeId = [];
        foreach ($categoryNodeStorageTransfers as $categoryNodeStorageTransfer) {
            foreach ($categoryNodeStorageTransfer->getParents() as $parentCategoryNodeStorageTransfer) {
                $this->collectAncestorNames($parentCategoryNodeStorageTransfer, $namesByNodeId, 0);
            }
        }

        return array_values($namesByNodeId);
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeStorageTransfer $categoryNodeStorageTransfer
     * @param array<int, string> $namesByNodeId
     * @param int $depth
     *
     * @return void
     */
    protected function collectAncestorNames(
        CategoryNodeStorageTransfer $categoryNodeStorageTransfer,
        array &$namesByNodeId,
        int $depth
    ): void {
        if ($depth > static::MAX_CATEGORY_ANCESTOR_DEPTH) {
            return;
        }

        $nodeId = $categoryNodeStorageTransfer->getNodeId();

        if ($nodeId !== null && isset($namesByNodeId[$nodeId])) {
            // Already visited (cycle guard) — the name is already collected, nothing more to do.
            return;
        }

        if ($nodeId !== null) {
            $namesByNodeId[$nodeId] = (string)$categoryNodeStorageTransfer->getName();
        }

        foreach ($categoryNodeStorageTransfer->getParents() as $parentCategoryNodeStorageTransfer) {
            $this->collectAncestorNames($parentCategoryNodeStorageTransfer, $namesByNodeId, $depth + 1);
        }
    }

    /**
     * @param array<string, mixed> $productData
     *
     * @return string
     */
    protected function findMerchantName(array $productData): string
    {
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
