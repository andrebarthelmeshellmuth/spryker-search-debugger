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
     * provide, because source fields are flattened into untagged arrays (e.g. `full-text` /
     * `full-text-boosted`) before indexing.
     *
     * Works document-driven, in two layers: reads the product's REAL indexed document (whose per-tier
     * arrays still hold one element per contributed source value), marks the elements containing the
     * token, and labels each element by matching it first against the known NAMED source values (title,
     * SKU, description, category, merchant name, ...), then — for elements no named source claims —
     * against the product's own searchable attribute values (labeled with the real attribute key, e.g.
     * "brand"). Elements neither layer identifies are still shown, under a generic "other indexed value"
     * label, so a document element degrades gracefully rather than disappearing.
     *
     * A value two or more sources contribute identically (e.g. a merchant name that happens to equal the
     * product title) is genuinely indistinguishable once merged into one document element — rather than
     * silently attributing it to just one, that row's `labelKeys` lists every colliding source, so the
     * page can show the ambiguity honestly instead of guessing.
     *
     * Tiers themselves are driven by $fieldBoosts (the query's real, live field=>boost pairs — see
     * `SprykerCommunity\Client\SearchDebug\Query\QueryFieldBoostReaderInterface`): however many fields the
     * query actually searched, not a fixed count, sorted by boost descending. There is no fallback for an
     * empty $fieldBoosts (e.g. a hand-typed URL, or a request whose search never went through
     * `SearchDebugQueryExpanderPlugin`) — `tiers` comes back empty rather than guessing at a field list or
     * a boost value that isn't real.
     *
     * @param string $productAbstractSku
     * @param string $token
     * @param string $localeName
     * @param array<string, int> $fieldBoosts The query's real field=>boost pairs, e.g.
     *   `['full-text' => 1, 'full-text-boosted' => 5]`. Empty means it couldn't be captured — `tiers`
     *   comes back empty, not a guessed default.
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
     * }|null Null when no product abstract has that SKU.
     */
    public function resolve(string $productAbstractSku, string $token, string $localeName, array $fieldBoosts = []): ?array;
}
