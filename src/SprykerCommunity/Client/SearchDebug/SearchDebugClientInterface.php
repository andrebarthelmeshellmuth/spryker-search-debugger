<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug;

interface SearchDebugClientInterface
{
    /**
     * Specification:
     * - Runs the given search string through the page index's query-time analyzer via the Elasticsearch `_analyze` API.
     * - The analyzer is resolved from the LIVE index's mapping (`search_analyzer` of the `full-text` field,
     *   with Elasticsearch's own fallback rules), so no analyzer names need configuring.
     * - Returns the resulting token strings in analyzer output order.
     * - Returns an empty array for an empty search string or when Elasticsearch is unreachable.
     *
     * @api
     *
     * @param string $searchString
     *
     * @return array<string>
     */
    public function getSearchStringTokens(string $searchString): array;

    /**
     * Specification:
     * - Runs $text through the page index's INDEX-time analyzer (resolved from the live index's mapping),
     *   via Elasticsearch's `_analyze` endpoint with `explain: true` — deliberately the index-time
     *   analyzer, not the query-time one `getSearchStringTokens()` uses: this answers "does $text contain
     *   token X", which is determined by what $text would be indexed as, not by how a query string gets
     *   tokenized.
     * - Returns each resulting token together with its start/end character offset into $text, so a caller
     *   can locate exactly where in the original text a given token came from (e.g. to highlight it).
     * - Returns an empty array for empty text or when Elasticsearch is unreachable.
     *
     * @api
     *
     * @param string $text
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTextTokenOffsets(string $text): array;

    /**
     * Specification:
     * - Runs $text through the page index's INDEX-time analyzer, same as `getTextTokenOffsets()`, but
     *   returns every intermediate stage instead of only the final one: each char filter (a single
     *   whole-text pseudo-token, offsets 0..length — char filters run before tokenization, so there are
     *   no per-token offsets yet), the tokenizer, and every token filter, in chain order.
     * - Offsets are consistent across stages (a Lucene invariant: they're always relative to the
     *   original $text, never re-based per stage), so a caller can reconstruct which token in an
     *   earlier stage produced a given token in a later one purely from offset containment.
     * - Returns an empty array for empty text or when Elasticsearch is unreachable.
     *
     * @api
     *
     * @param string $text
     *
     * @return array<array{operation: string, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getTextAnalysisStages(string $text): array;

    /**
     * Specification:
     * - Reads one entity's document from the page search index, exactly as it was indexed.
     * - The document id is generated through the synchronization service key builder (the same format
     *   the publish pipeline writes with, e.g. `product_abstract:de:de_de:238`), using the current store.
     * - Returns the raw document source (e.g. the `full-text`/`full-text-boosted` arrays with one element
     *   per contributed source value), or null when the document does not exist or Elasticsearch is
     *   unreachable.
     *
     * @api
     *
     * @param string $resourceName
     * @param string $identifier
     * @param string $localeName
     *
     * @return array<string, mixed>|null
     */
    public function findPageDocumentData(string $resourceName, string $identifier, string $localeName): ?array;
}
