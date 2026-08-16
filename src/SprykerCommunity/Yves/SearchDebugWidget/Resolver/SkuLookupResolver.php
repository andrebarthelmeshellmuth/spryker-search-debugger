<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use Spryker\Client\Catalog\CatalogClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;

class SkuLookupResolver implements SkuLookupResolverInterface
{
    /**
     * Matches `TokenSourceResolver::RESOURCE_NAME_PRODUCT_ABSTRACT` — kept local rather than a shared
     * constant, same standalone-class posture the rest of this package's resolvers already keep.
     *
     * @var string
     */
    protected const RESOURCE_NAME_PRODUCT_ABSTRACT = 'product_abstract';

    /**
     * @var string
     */
    protected const MAPPING_TYPE_SKU = 'sku';

    /**
     * Matches `Spryker\Client\Catalog\Plugin\Config\CatalogSearchConfigBuilder::PARAMETER_NAME_PAGE` — a
     * literal string rather than a hard dependency on that plugin class, since only the constant's VALUE
     * is needed, and it is the one Spryker itself has shipped for every catalog search page for years.
     *
     * @var string
     */
    protected const PARAMETER_NAME_PAGE = 'page';

    /**
     * Upper bound on how many result-set items {@see resolveRankPosition()} will scan through before
     * giving up and reporting "beyond this many, unknown position" — matches the original design's own
     * `size=1000` raw-query proposal, applied here as a scan cap across paginated `catalogSearch()` calls
     * instead (see that method's docblock for why).
     *
     * @var int
     */
    protected const RANK_SCAN_LIMIT = 1000;

    /**
     * Hard ceiling on how many internal `catalogSearch()` calls one lookup may issue, independent of
     * {@see RANK_SCAN_LIMIT} — protects against an installation whose items-per-page is configured
     * unusually small (e.g. 1), where the scan-limit alone could otherwise mean hundreds of requests.
     *
     * @var int
     */
    protected const MAX_SCAN_REQUESTS = 40;

    /**
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface $productStorageClient
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     * @param \Spryker\Client\Catalog\CatalogClientInterface $catalogClient
     */
    public function __construct(
        protected ProductStorageClientInterface $productStorageClient,
        protected SearchDebugClientInterface $searchDebugClient,
        protected CatalogClientInterface $catalogClient,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sku
     * @param string $searchString
     * @param array<string, mixed> $requestParameters
     * @param string $localeName
     */
    public function resolve(string $sku, string $searchString, array $requestParameters, string $localeName): SkuLookupResult
    {
        $productData = $this->productStorageClient->findProductAbstractStorageDataByMapping(
            static::MAPPING_TYPE_SKU,
            $sku,
            $localeName,
        );

        if ($productData === null) {
            return new SkuLookupResult(SkuLookupResult::STATUS_NOT_FOUND, null, null, null, null, false);
        }

        $idProductAbstract = (int)$productData['id_product_abstract'];
        $productSku = (string)($productData['sku'] ?? $sku);
        $productTitle = (string)($productData['name'] ?? '');

        $documentData = $this->searchDebugClient->findPageDocumentData(
            static::RESOURCE_NAME_PRODUCT_ABSTRACT,
            (string)$idProductAbstract,
            $localeName,
        );

        if ($documentData === null) {
            return new SkuLookupResult(SkuLookupResult::STATUS_NOT_INDEXED, $productSku, $productTitle, $idProductAbstract, null, false);
        }

        [$rankPosition, $beyondScanWindow] = $this->resolveRankPosition($idProductAbstract, $searchString, $requestParameters);

        return new SkuLookupResult(SkuLookupResult::STATUS_FOUND, $productSku, $productTitle, $idProductAbstract, $rankPosition, $beyondScanWindow);
    }

    /**
     * The original design called for a single raw `size=1000, _source`-limited-to-id Elasticsearch query
     * to determine a product's rank, deliberately NOT paginating through Yves's own result set (see this
     * package's own planning notes). That raw-query approach would need to hand-reconstruct the exact
     * bool query the shop's real query-expander plugin stack builds for the CURRENT request (every active
     * facet, sort, and boost) — a second, parallel query-building path that would silently drift out of
     * sync the moment a project customizes its own query plugins.
     *
     * This re-runs the shop's OWN `CatalogClient::catalogSearch()` instead — the exact same query
     * construction the SRP itself uses, guaranteed never to drift — but capped to a bounded scan instead
     * of walking every page a user could ever reach: one internal request per page, at whatever
     * items-per-page the current request already validated, until the product is found, the result set is
     * exhausted, or {@see RANK_SCAN_LIMIT} items (or {@see MAX_SCAN_REQUESTS} requests) have been scanned.
     * Still a single round trip from the BROWSER's perspective — only the several internal Elasticsearch
     * calls behind it are new, and each is materially cheaper than the raw query being avoided.
     *
     * @param int $idProductAbstract
     * @param string $searchString
     * @param array<string, mixed> $requestParameters
     *
     * @return array{0: int|null, 1: bool}
     */
    protected function resolveRankPosition(int $idProductAbstract, string $searchString, array $requestParameters): array
    {
        $scanned = 0;
        $page = 1;

        for ($request = 0; $request < static::MAX_SCAN_REQUESTS; $request++) {
            $pageParameters = $requestParameters;
            $pageParameters[static::PARAMETER_NAME_PAGE] = $page;

            $result = $this->catalogClient->catalogSearch($searchString, $pageParameters);
            $products = (array)($result['products'] ?? []);

            if ($products === []) {
                return [null, false];
            }

            foreach ($products as $product) {
                $scanned++;

                if ((int)($product['id_product_abstract'] ?? 0) === $idProductAbstract) {
                    return [$scanned, false];
                }

                if ($scanned >= static::RANK_SCAN_LIMIT) {
                    return [null, true];
                }
            }

            $maxPage = (int)($result['pagination']['maxPage'] ?? $page);

            if ($page >= $maxPage) {
                return [null, false];
            }

            $page++;
        }

        return [null, true];
    }
}
