<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface TokenSourceResolverInterface
{
    /**
     * Shows, for one product and one already-matched search token, which of the product's raw source
     * fields the token actually came from — information Elasticsearch's own explain output cannot
     * provide, because source fields are flattened into two untagged arrays (`full-text` /
     * `full-text-boosted`) before indexing.
     *
     * Works document-driven: reads the product's REAL indexed document (whose per-tier arrays still
     * hold one element per contributed source value), marks the elements containing the token, and
     * labels each element by matching it against the known source values — elements no known source
     * claims (e.g. searchable product attributes, custom map expanders) are still shown, under a
     * generic "other indexed value" label.
     *
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
     * }|null Null when no product abstract has that SKU.
     */
    public function resolve(string $productAbstractSku, string $token, string $localeName): ?array;
}
