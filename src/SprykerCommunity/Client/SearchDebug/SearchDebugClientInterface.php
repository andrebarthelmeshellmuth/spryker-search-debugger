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
     * - Runs $text through the page index's INDEX-time analyzer by default (resolved from the live
     *   index's mapping), via Elasticsearch's `_analyze` endpoint with `explain: true` — deliberately the
     *   index-time analyzer, not the query-time one `getSearchStringTokens()` uses: this answers "does
     *   $text contain token X", which is determined by what $text would be indexed as, not by how a
     *   query string gets tokenized.
     * - Pass `$useSearchAnalyzer = true` to trace a QUERY string's own tokenization instead (the text was
     *   never indexed, only searched) — e.g. for an analysis path reached from one of the SRP overlay's
     *   own matched query tokens, rather than from a product's indexed content.
     * - Returns each resulting token together with its start/end character offset into $text, so a caller
     *   can locate exactly where in the original text a given token came from (e.g. to highlight it).
     * - Returns an empty array for empty text or when Elasticsearch is unreachable.
     *
     * @api
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTextTokenOffsets(string $text, bool $useSearchAnalyzer = false): array;

    /**
     * Specification:
     * - Same as `getTextTokenOffsets()`, batched into ONE `_analyze` call for several distinct texts
     *   instead of one call per text — for a caller that needs offsets for many pieces of text at once
     *   (e.g. every element of a product's document).
     * - Empty and duplicate texts are dropped before the batched call; the same text given twice appears
     *   once in the result, keyed by its own text.
     * - Returns an empty array for an empty (or all-empty/all-duplicate) $texts list.
     *
     * @api
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getTextTokenOffsetsForTexts(array $texts): array;

    /**
     * Specification:
     * - Runs $text through the page index's INDEX-time analyzer, same as `getTextTokenOffsets()`, but
     *   returns every intermediate stage instead of only the final one: each char filter (a single
     *   whole-text pseudo-token, offsets 0..length — char filters run before tokenization, so there are
     *   no per-token offsets yet), the tokenizer, and every token filter, in chain order.
     * - Offsets are consistent across stages (a Lucene invariant: they're always relative to the
     *   original $text, never re-based per stage), so a caller can reconstruct which token in an
     *   earlier stage produced a given token in a later one purely from offset containment.
     * - Each stage's `definition` is that component's own configuration as declared in the live index's
     *   analysis settings (e.g. "edge_ngram (min_gram: 2, max_gram: 20)"), or null when the component is
     *   a built-in Elasticsearch one used by name only (e.g. "lowercase", "standard") — nothing custom
     *   was configured for it.
     * - Returns an empty array for empty text or when Elasticsearch is unreachable.
     *
     * - Pass `$useSearchAnalyzer = true` to trace a QUERY string's own tokenization instead of a piece of
     *   indexed product text — see {@see getTextTokenOffsets()}'s parameter of the same name.
     *
     * @api
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getTextAnalysisStages(string $text, bool $useSearchAnalyzer = false): array;

    /**
     * Specification:
     * - Same underlying `_analyze?explain=true` data as `getTextAnalysisStages()`, restructured into a
     *   branching diagram laid out by STAGE rather than by lineage: `stages` is one entry per analyzer
     *   stage (char filter, tokenizer, token filter, in chain order), each carrying every token alive at
     *   that point (`nodes`); `edges` connects a stage's nodes to the specific nodes they produced in the
     *   NEXT stage, so every fan-out a filter produces (synonym expansion, decompounding, ngrams, ...) is
     *   visible as multiple edges out of one node rather than collapsed away. Nodes are linked
     *   stage-to-stage via Elasticsearch's own per-token `position` field — a correlation, not a
     *   guaranteed parent pointer (the API exposes none); see the implementation's own docblock for the
     *   one known edge case this can misattribute. A node with no matching position in the previous
     *   stage simply has no incoming edge.
     * - A stemming stage's `label` reads "stem: X" rather than the generic "filter: X" every other token
     *   filter gets, and its `isStem` flag is `true`.
     * - A token a filter drops outright (a stop-word or min-length filter is the common case) gets a
     *   synthetic `isRemoved: true` node (`token: "∅"`) injected into the NEXT stage's own row, linked by
     *   a normal edge — so a dead end is always visible, never silently absent.
     * - Returns `{stages: [], edges: []}` for empty text or when Elasticsearch is unreachable.
     * - Pass `$useSearchAnalyzer = true` to trace a QUERY string's own tokenization instead of a piece of
     *   indexed product text — see {@see getTextTokenOffsets()}'s parameter of the same name.
     *
     * @api
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function getTextAnalysisTree(string $text, bool $useSearchAnalyzer = false): array;

    /**
     * Specification:
     * - Looks up one named tokenizer/filter/char_filter definition from the page index's live analysis
     *   settings — the FULL, untruncated config, unlike `getTextAnalysisStages()`'s `definition` string,
     *   which previews long config lists (e.g. a `synonym` filter's `synonyms`) rather than showing them
     *   in full. Meant for a "view full definition" page reached from a stage whose `definitionTruncated`
     *   came back true.
     * - $componentKind must be one of `tokenizer`, `filter`, `char_filter` (the same three values that
     *   appear as `componentKind` in `getTextAnalysisStages()`'s result).
     * - Returns null when $componentKind isn't one of those three, or no component of that kind has that
     *   name (e.g. it's a built-in Elasticsearch component used by name only, never customized, or
     *   Elasticsearch is unreachable).
     *
     * @api
     *
     * @param string $componentKind
     * @param string $componentName
     *
     * @return array{name: string, type: string, config: array<string, mixed>}|null
     */
    public function getComponentConfig(string $componentKind, string $componentName): ?array;

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
