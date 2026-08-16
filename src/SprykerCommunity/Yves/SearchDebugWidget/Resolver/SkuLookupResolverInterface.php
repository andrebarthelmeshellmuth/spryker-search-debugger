<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface SkuLookupResolverInterface
{
    /**
     * Resolves the three "why isn't this SKU showing up" outcomes (see {@see SkuLookupResult}'s own
     * constants): SKU doesn't resolve to any product, resolves but has no search document, or resolves
     * and has one — in which case its position in the CURRENT search result set is also determined, via
     * the shop's own catalog search re-run one page at a time (not a raw Elasticsearch query — see
     * {@see SkuLookupResolver::resolveRankPosition()}'s own docblock for why that's a deliberate
     * simplification of the originally-planned approach).
     *
     * @param string $sku
     * @param string $searchString The CURRENT search result set's own query string — determines what
     *   "found in the result set" even means.
     * @param array<string, mixed> $requestParameters The current request's own query parameters (facets,
     *   sort, items-per-page, ...) — reused verbatim for the rank re-run so it reflects the SAME filtered
     *   view the admin is actually looking at, only the `page` parameter is overridden per scan step.
     * @param string $localeName
     */
    public function resolve(string $sku, string $searchString, array $requestParameters, string $localeName): SkuLookupResult;
}
