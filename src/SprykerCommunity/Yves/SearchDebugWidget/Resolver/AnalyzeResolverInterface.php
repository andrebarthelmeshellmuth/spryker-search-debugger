<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface AnalyzeResolverInterface
{
    /**
     * Assembles the analyze page's own data: the current query's own tokens (top row) and, per document
     * field, every distinct WORD that field's raw (pre-tokenization) text contains — each already flagged
     * with whether it shares at least one produced token with the query, so the page can highlight
     * near-misses before anything is even clicked. Building the actual branching analysis TREE for a
     * clicked word/token is a separate step (see `SearchDebugClientInterface::getTextAnalysisTree()`,
     * called directly from `AnalyzeController` per pinned word) — this resolver only assembles what gets
     * shown BEFORE a click.
     *
     * @param string $sku
     * @param string $searchString
     * @param string $localeName
     *
     * @return array{
     *     productSku: string,
     *     productTitle: string,
     *     queryTokens: array<string>,
     *     queryTokenOrigins: array<string, string>,
     *     fieldRows: array<int, array{labelKey: string, words: array<int, array{word: string, matchesQuery: bool}>}>,
     * }|null Null when $sku does not resolve to a product at all.
     */
    public function resolve(string $sku, string $searchString, string $localeName): ?array;
}
