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
use Spryker\Client\CategoryStorage\CategoryStorageClientInterface;
use Spryker\Client\MerchantStorage\MerchantStorageClientInterface;
use Spryker\Client\ProductCategoryStorage\ProductCategoryStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use Spryker\Client\Store\StoreClientInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;

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
     * Both abstract and concrete storage documents carry a flat `attributes` map (machine attribute key =>
     * localized value, e.g. `{"brand": "Example Company", "farbe": "Rot"}`) — confirmed live against this
     * shop's own product storage data. Already fetched by this resolver (no extra client call, no Zed
     * round trip needed) via the same `findProductAbstractStorageDataByMapping()`/
     * `getBulkProductConcreteStorageData()` calls used for name/sku/description.
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
     * register more fields than these two (see `SOURCE_DEFINITIONS` below) — this constant list only
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
     * Label for document elements no known source AND no product attribute claims — e.g. a custom map
     * expander plugin contributing something this resolver has no way to identify.
     *
     * @var string
     */
    protected const LABEL_KEY_OTHER = 'search_debug.token_source.field.other';

    /**
     * Fallback tier label for a field $fieldBoosts reports that isn't one of the two well-known
     * `PageIndexMap` constants below (see {@link TIER_LABEL_KEYS}) — interpolates the raw field name and
     * boost directly rather than requiring a translation key per possible project-specific field name.
     *
     * @var string
     */
    protected const LABEL_KEY_TIER_GENERIC = 'search_debug.token_source.tier.generic';

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
     * indexed document does. It only assigns readable labels to document elements it can identify; an
     * element no entry claims is checked against the product's own attribute values next (see
     * `collectAttributeLabelsByValue()`), and only falls back to the generic {@link LABEL_KEY_OTHER} label
     * if neither identifies it — so a new contributor on the indexing side degrades gracefully rather than
     * disappearing.
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
     * Nicer labels for the two well-known `PageIndexMap` fields, when $fieldBoosts (the query's real,
     * live field=>boost pairs) reports them — actual tier ORDER at render time is boost-descending, driven
     * by $fieldBoosts, not by this list; a field not listed here still gets its own tier, with a generic
     * label (see {@link LABEL_KEY_TIER_GENERIC}), not silently dropped.
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
     */
    public function __construct(
        ProductStorageClientInterface $productStorageClient,
        ProductCategoryStorageClientInterface $productCategoryStorageClient,
        CategoryStorageClientInterface $categoryStorageClient,
        MerchantStorageClientInterface $merchantStorageClient,
        SearchDebugClientInterface $searchDebugClient,
        StoreClientInterface $storeClient,
        TokenHighlighterInterface $tokenHighlighter,
    ) {
        $this->productStorageClient = $productStorageClient;
        $this->productCategoryStorageClient = $productCategoryStorageClient;
        $this->categoryStorageClient = $categoryStorageClient;
        $this->merchantStorageClient = $merchantStorageClient;
        $this->searchDebugClient = $searchDebugClient;
        $this->storeClient = $storeClient;
        $this->tokenHighlighter = $tokenHighlighter;
    }

    /**
     * @param string $productAbstractSku
     * @param string $token
     * @param string $localeName
     * @param array<string, int> $fieldBoosts The query's real field=>boost pairs (see
     *   `SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface`). Empty means that
     *   couldn't be captured (e.g. a hand-typed URL, or a request whose search never went through
     *   `SearchDebugQueryExpanderPlugin`) — `tiers` is then empty too, rather than guessing at which
     *   fields a query might have used.
     *
     * @return array{
     *     productTitle: string,
     *     productSku: string,
     *     tiers: array<int, array{
     *         key: string,
     *         labelKey: string,
     *         boost: int,
     *         rows: array<int, array{labelKeys: array<int, string>, matched: bool, highlightedHtml: string|null, element: string|null, matches: array<int, array{token: string, startOffset: int, endOffset: int}>}>,
     *     }>,
     * }|null
     */
    public function resolve(string $productAbstractSku, string $token, string $localeName, array $fieldBoosts = []): ?array
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

        // Fetched once, here, and passed into both of the methods below — they used to each independently
        // fetch the same bulk concrete-storage rows for the same product/locale, doubling this request's
        // storage round trips for identical data.
        $concreteStorageData = $this->fetchConcreteStorageData($productData, $localeName);

        $sourceKeysByValue = $this->collectSourceKeysByValue($productData, $localeName, $concreteStorageData);
        $attributeLabelByValue = $this->collectAttributeLabelsByValue($productData, $concreteStorageData);

        return [
            'productTitle' => (string)($productData[static::STORAGE_KEY_NAME] ?? ''),
            'productSku' => (string)($productData[static::STORAGE_KEY_SKU] ?? ''),
            'tiers' => $this->buildTiers($documentData ?? [], $sourceKeysByValue, $attributeLabelByValue, $token, $fieldBoosts),
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
            array_values(array_map('intval', (array)$productConcreteIds)),
            $localeName,
        );
    }

    /**
     * Attributes each tier's real document elements: analyzes every element with the index-time
     * analyzer, marks the ones containing the token, and labels each element via the per-tier
     * value-to-source lookup — falling back to the generic "other indexed value" label for elements no
     * known source or attribute claims.
     *
     * Which tiers exist, and in what order, is driven entirely by $fieldBoosts — the query's real, live
     * field=>boost pairs — sorted boost-descending, not a fixed count: a query searching three fields
     * shows three tiers, one searching five shows five. No fallback: an empty $fieldBoosts means the
     * fields a query actually used could not be captured, and this legitimately has nothing to show
     * rather than guessing at a field list or a boost value that isn't real.
     *
     * @param array<string, mixed> $documentData
     * @param array<string, array<string, array<int, string>>> $sourceKeysByValue
     * @param array<string, array<int, string>> $attributeLabelByValue
     * @param string $token
     * @param array<string, int> $fieldBoosts
     *
     * @return array<int, array{
     *     key: string,
     *     labelKey: string,
     *     boost: int,
     *     rows: array<int, array{labelKeys: array<int, string>, matched: bool, highlightedHtml: string|null, element: string|null, matches: array<int, array{token: string, startOffset: int, endOffset: int}>}>,
     * }>
     */
    protected function buildTiers(
        array $documentData,
        array $sourceKeysByValue,
        array $attributeLabelByValue,
        string $token,
        array $fieldBoosts,
    ): array {
        arsort($fieldBoosts);

        $elementsByTier = [];
        foreach ($fieldBoosts as $tier => $boost) {
            $elementsByTier[$tier] = array_map('strval', (array)($documentData[$tier] ?? []));
        }

        $this->warmTokenOffsetsCache(array_merge(...array_values($elementsByTier)));

        $tiers = [];
        foreach ($fieldBoosts as $tier => $boost) {
            $tiers[] = [
                'key' => $tier,
                'labelKey' => static::TIER_LABEL_KEYS[$tier] ?? static::LABEL_KEY_TIER_GENERIC,
                'boost' => $boost,
                'rows' => $this->buildTierRows($elementsByTier[$tier], $sourceKeysByValue[$tier] ?? [], $attributeLabelByValue, $token),
            ];
        }

        return $tiers;
    }

    /**
     * Pre-fetches token offsets for every element across every tier in ONE batched Elasticsearch call
     * (see {@see \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface::getTextTokenOffsetsForTexts()}),
     * instead of {@see buildTierRows()}/{@see findTokenMatches()} triggering one blocking `_analyze` call
     * per element as they go — a product with several variants can easily have 10-20+ distinct elements.
     *
     * Deliberately NOT a `$uncachedElements[$element] = true` dict with `array_keys()` at the end: PHP
     * silently coerces a purely-numeric STRING array key (e.g. an element that happens to read like a
     * plain number) to a real int key, so `array_keys()` would leak an `int` into `$uncachedElements`
     * where every OTHER entry is a `string` — a real, live-reproduced bug (a numeric-looking element
     * broke the batched analyze call with a `TypeError`, `int` given where `string` was expected). Same
     * class of coercion {@see splitByQueryTokens()} already documents guarding against elsewhere in this
     * package — `array_unique()` over a plain list compares by VALUE, never touches keys, so it can't
     * silently retype anything.
     *
     * @param array<int, string> $elements
     *
     * @return void
     */
    protected function warmTokenOffsetsCache(array $elements): void
    {
        $uncachedElements = array_values(array_unique(array_filter(
            $elements,
            fn (string $element): bool => trim($element) !== '' && !isset($this->tokenOffsetsCache[$element]),
        )));

        if ($uncachedElements === []) {
            return;
        }

        $this->tokenOffsetsCache += $this->searchDebugClient->getTextTokenOffsetsForTexts($uncachedElements);
    }

    /**
     * One row per MATCHED document element (in canonical source-definition order, see
     * {@see collectSourceKeysByValue()}) — each carries its own raw `element` text, so a caller (the
     * token-source page's magnifying-glass link) can open the analysis-path page for that EXACT element,
     * not a source in the abstract. A source with no matching element at all still gets ONE summary "no
     * match" row (nothing to link to there, so no reason to show one per element). A value two or more
     * sources both claim renders with multiple `labelKeys` instead of picking one and silently dropping
     * the rest — the caller is expected to join and display all of them, honestly communicating that the
     * value can't be attributed to just one. Elements no named source claims are matched against the
     * product's own attribute values next (label = the real attribute key); anything still unidentified
     * follows as one row each, ALWAYS showing their text — for those, the value itself is the diagnostic
     * information.
     *
     * @param array<int, string> $elements
     * @param array<string, array<int, string>> $sourceKeysByValue
     * @param array<string, array<int, string>> $attributeLabelByValue
     * @param string $token
     *
     * @return array<int, array{labelKeys: array<int, string>, matched: bool, highlightedHtml: string|null, element: string|null, matches: array<int, array{token: string, startOffset: int, endOffset: int}>}>
     */
    protected function buildTierRows(array $elements, array $sourceKeysByValue, array $attributeLabelByValue, string $token): array
    {
        $matchedRowsByGroupKey = [];
        $sourceKeysByGroupKey = [];
        $otherRows = [];
        $seenElementsByGroupKey = [];

        foreach ($elements as $element) {
            if (trim($element) === '') {
                continue;
            }

            // Filtered through the SAME overlap rule highlight() itself applies internally, and stored as
            // THIS row's 'matches' below — never the raw, unfiltered list — so a row's magnifying-glass
            // links (built one per rendered `<mark>`, matched up by array position) can never drift out of
            // sync with which marks actually got rendered.
            $matches = $this->tokenHighlighter->filterRenderable($this->findTokenMatches($element, $token));
            $sourceKeys = $sourceKeysByValue[$element] ?? [];

            if ($sourceKeys === []) {
                $attributeKeys = $attributeLabelByValue[$element] ?? [];
                $labelKeys = $attributeKeys !== [] ? $attributeKeys : [static::LABEL_KEY_OTHER];
                $groupKey = implode('|', $labelKeys);

                // Two raw document elements can be byte-identical (e.g. a concrete description that
                // happens to equal the abstract's) — one row per distinct (label, text) pair is enough;
                // a second identical row would only repeat information already shown.
                if (isset($seenElementsByGroupKey[$groupKey][$element])) {
                    continue;
                }

                $seenElementsByGroupKey[$groupKey][$element] = true;

                $otherRows[] = [
                    'labelKeys' => $labelKeys,
                    'matched' => $matches !== [],
                    'highlightedHtml' => $this->tokenHighlighter->highlight($element, $matches),
                    'element' => $matches !== [] ? $element : null,
                    'matches' => $matches,
                ];

                continue;
            }

            // $sourceKeys is already in canonical order (built that way by collectSourceKeysByValue()),
            // so the group key doubles as a stable identity for "this exact combination of sources".
            $groupKey = implode('|', $sourceKeys);
            $sourceKeysByGroupKey[$groupKey] = $sourceKeys;

            if ($matches === []) {
                continue;
            }

            // Same de-duplication as above: a value appearing as two separate raw elements under the
            // SAME source attribution (e.g. abstract description == concrete description) would otherwise
            // render as two identical rows.
            if (isset($seenElementsByGroupKey[$groupKey][$element])) {
                continue;
            }

            $seenElementsByGroupKey[$groupKey][$element] = true;

            $matchedRowsByGroupKey[$groupKey][] = [
                'labelKeys' => array_map(fn (string $sourceKey): string => static::SOURCE_DEFINITIONS[$sourceKey]['labelKey'], $sourceKeys),
                'matched' => true,
                'highlightedHtml' => $this->tokenHighlighter->highlight($element, $matches),
                'element' => $element,
                'matches' => $matches,
            ];
        }

        $canonicalOrder = array_flip(array_keys(static::SOURCE_DEFINITIONS));
        uasort($sourceKeysByGroupKey, fn (array $a, array $b): int => $canonicalOrder[$a[0]] <=> $canonicalOrder[$b[0]]);

        $rows = [];
        foreach ($sourceKeysByGroupKey as $groupKey => $sourceKeys) {
            if (isset($matchedRowsByGroupKey[$groupKey])) {
                array_push($rows, ...$matchedRowsByGroupKey[$groupKey]);

                continue;
            }

            $rows[] = [
                'labelKeys' => array_map(fn (string $sourceKey): string => static::SOURCE_DEFINITIONS[$sourceKey]['labelKey'], $sourceKeys),
                'matched' => false,
                'highlightedHtml' => null,
                'element' => null,
                'matches' => [],
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
     * Builds the per-tier lookup of raw source value => source keys used to label document elements.
     * The indexing pipeline writes each source value into its tier verbatim, so plain string equality
     * identifies an element's origin — but the SAME string can legitimately be contributed by two
     * DIFFERENT sources (e.g. a merchant name that happens to equal the product title): once merged into
     * one document element, those are genuinely indistinguishable, not a case this resolver can resolve
     * with more cleverness. Rather than silently keeping one source and dropping the other (the earlier
     * behavior), every colliding source key is kept, in canonical definition order — {@see buildTierRows()}
     * renders a value with multiple source keys as one honestly-ambiguous row instead of guessing.
     *
     * @param array<string, mixed> $productData
     * @param string $localeName
     * @param array<int, array<string, mixed>> $concreteStorageData Already-fetched concrete storage rows
     *   for this product (see {@see resolve()}) — reused here instead of re-fetching the same bulk data a
     *   second time within one request.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    protected function collectSourceKeysByValue(array $productData, string $localeName, array $concreteStorageData): array
    {
        $storeName = $this->storeClient->getCurrentStore()->getName();

        $valuesBySourceKey = [
            static::KEY_TITLE => [(string)($productData[static::STORAGE_KEY_NAME] ?? '')],
            static::KEY_SKU => [(string)($productData[static::STORAGE_KEY_SKU] ?? '')],
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
     *   for this product (see {@see resolve()}) — reused here instead of re-fetching the same bulk data a
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
            $concreteValues[static::KEY_CONCRETE_NAMES][] = (string)($concreteData[static::STORAGE_KEY_NAME] ?? '');
            $concreteValues[static::KEY_CONCRETE_SKUS][] = (string)($concreteData[static::STORAGE_KEY_SKU] ?? '');
            $concreteValues[static::KEY_CONCRETE_DESCRIPTIONS][] = (string)($concreteData[static::STORAGE_KEY_DESCRIPTION] ?? '');
        }

        return $concreteValues;
    }

    /**
     * Labels document elements no NAMED source claims (see `SOURCE_DEFINITIONS`) by matching them against
     * the product's own searchable attribute values — both abstract- and concrete-level `attributes`, a
     * flat machine-key => localized-value map already present on both storage documents. This means a
     * shop's Search Preferences attributes (Zed > Catalog > Manage Attributes) show up under their real
     * attribute key instead of the generic "other indexed value" label — with zero Zed calls, since this
     * data is already reachable via the same Yves storage clients this resolver already uses.
     *
     * A value shared by two different attributes now keeps EVERY colliding attribute key, same as
     * `collectSourceKeysByValue()`'s NAMED sources — {@see \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenSourceResolver::buildTierRows()}
     * renders a value with multiple attribute keys as one honestly-ambiguous row instead of picking one.
     *
     * @param array<string, mixed> $productData
     * @param array<int, array<string, mixed>> $concreteStorageData Already-fetched concrete storage rows
     *   for this product (see {@see resolve()}) — reused here instead of re-fetching the same bulk data a
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
     *
     * @return void
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
     * Recursively walks each direct category's ancestor chain. `CategoryNodeStorageTransfer::getParents()`
     * returns the IMMEDIATE parent only, each one itself carrying its own further-nested `getParents()`,
     * terminating at the root category with an empty list — confirmed live against real category storage
     * data (a 4-level chain, e.g. node -> parent -> grandparent -> root, each level's `node_id` correctly
     * populated). The recursion below is therefore genuinely necessary, not a defensive no-op for an
     * already-flattened list.
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
        int $depth,
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
