<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use Elastica\Client;
use Elastica\Exception\ExceptionInterface;
use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\SearchIndexFieldTransfer;
use Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaMapper;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class SearchStringAnalyzer implements SearchStringAnalyzerInterface
{
    /**
     * @param \Elastica\Client $elasticaClient
     * @param \Spryker\Client\SearchElasticsearch\Index\IndexNameResolver\IndexNameResolverInterface $indexNameResolver
     * @param \SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface $indexSchemaReader
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     * @param \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface $componentDefinitionFormatter
     */
    public function __construct(
        protected Client $elasticaClient,
        protected IndexNameResolverInterface $indexNameResolver,
        protected IndexSchemaReaderInterface $indexSchemaReader,
        protected SearchDebugConfig $config,
        protected ComponentDefinitionFormatterInterface $componentDefinitionFormatter,
    ) {
    }

    /**
     * @param string $searchString
     *
     * @return array<string>
     */
    public function getTokens(string $searchString): array
    {
        if ($searchString === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $tokenData = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $searchString,
                    'analyzer' => $this->resolveSearchAnalyzerName(),
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return array_column($tokenData, 'token');
    }

    /**
     * Deliberately the INDEX-time analyzer BY DEFAULT, not the search-time one `getTokens()` above uses:
     * this method answers "does this piece of product text contain token X", and that's determined
     * entirely by what the text was indexed as — the query-time analyzer only ever tokenizes the query
     * string, it never touches document content. This matters whenever an analyzer transforms text
     * asymmetrically between index- and query-time — ngram/edge-ngram filters, decompounding, synonym
     * expansion, and stemming are all common examples — because only the index-time analyzer that
     * actually produced a document's tokens can explain why a query token matched it. E.g., in a basic
     * shop using an edge-ngram index analyzer (search-as-you-type prefix matching), a query token like
     * "öl" can legitimately match a document that only contains "Ölpapier" — the search-time analyzer
     * alone could never explain that match.
     *
     * `$useSearchAnalyzer` exists for the opposite question: tracing how a QUERY string itself (not
     * product content) was tokenized — the text in that case was never indexed, only searched, so the
     * search-time analyzer is the only one that actually processed it.
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTokenOffsets(string $text, bool $useSearchAnalyzer = false): array
    {
        if ($text === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $text,
                    'analyzer' => $useSearchAnalyzer ? $this->resolveSearchAnalyzerName() : $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return $this->mapTokenDetail($detail);
    }

    /**
     * Same as {@see getTokenOffsets()}, batched into ONE `_analyze` call for several distinct texts
     * instead of one call per text — e.g. every element of a product's document (title, every concrete
     * name/SKU, descriptions, category names, merchant name) can be analyzed in a single round trip
     * instead of one blocking HTTP call per element.
     *
     * Elasticsearch's `_analyze` API accepts an array for `text`, treating it like a multi-valued field:
     * offsets continue cumulatively across the whole array rather than resetting to 0 per text, with
     * exactly ONE code-unit gap inserted between consecutive texts (confirmed live against this package's
     * real index: two 2-character texts analyzed together report offsets [0,2) and [3,5), never
     * [0,2)/[2,4)) — {@see rebaseTokensByText()} uses that gap to split the flat token list back into
     * one list per original text, with offsets rebased to be relative to THAT text again, exactly as if
     * it had been analyzed alone. If any resulting offset ever falls outside its own text's bounds (the
     * 1-unit-gap assumption not holding against some other Elasticsearch version/configuration), this
     * degrades to one {@see getTokenOffsets()} call per text instead of trusting a possibly-misaligned
     * batched result — the batching is a pure optimization, never a correctness trade-off.
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getTokenOffsetsForTexts(array $texts): array
    {
        $texts = array_values(array_unique(array_filter($texts, fn (string $text): bool => $text !== '')));

        if ($texts === []) {
            return [];
        }

        if (count($texts) === 1) {
            return [$texts[0] => $this->getTokenOffsets($texts[0])];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $texts,
                    'analyzer' => $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return array_fill_keys($texts, []);
        }

        $tokensByText = $this->rebaseTokensByText($texts, $this->mapTokenDetail($detail));

        if ($tokensByText === null) {
            $tokensByText = [];
            foreach ($texts as $text) {
                $tokensByText[$text] = $this->getTokenOffsets($text);
            }
        }

        return $tokensByText;
    }

    /**
     * @param array<string> $texts
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>|null Null when
     *   a rebased offset falls outside its own text's bounds — the gap assumption {@see getTokenOffsetsForTexts()}
     *   documents didn't hold for this response, and the caller should fall back to one call per text.
     */
    protected function rebaseTokensByText(array $texts, array $tokens): ?array
    {
        $boundaries = [];
        $cursor = 0;

        foreach ($texts as $text) {
            $length = Utf16CodeUnitConverter::lengthOf(Utf16CodeUnitConverter::toUtf16($text));
            $boundaries[] = ['text' => $text, 'start' => $cursor, 'end' => $cursor + $length];
            // The single code-unit gap Elasticsearch inserts between consecutive array-`text` values —
            // see this method's caller for the live-confirmed measurement.
            $cursor += $length + 1;
        }

        $tokensByText = array_fill_keys($texts, []);

        foreach ($tokens as $token) {
            $boundary = null;

            foreach ($boundaries as $candidate) {
                if ($token['startOffset'] >= $candidate['start'] && $token['startOffset'] < $candidate['end']) {
                    $boundary = $candidate;

                    break;
                }
            }

            if ($boundary === null) {
                return null;
            }

            $startOffset = $token['startOffset'] - $boundary['start'];
            $endOffset = $token['endOffset'] - $boundary['start'];

            if ($startOffset < 0 || $endOffset > ($boundary['end'] - $boundary['start'])) {
                return null;
            }

            $tokensByText[$boundary['text']][] = [
                'token' => $token['token'],
                'startOffset' => $startOffset,
                'endOffset' => $endOffset,
            ];
        }

        return $tokensByText;
    }

    /**
     * Full per-stage breakdown of the index-time analyzer's pipeline — every char filter (whole-text
     * transformations, before tokenization), the tokenizer, and every token filter, in chain order.
     * `getTokenOffsets()` above only needs the FINAL stage's tokens; this keeps every intermediate
     * stage instead of collapsing to the last one, so a caller can reconstruct the transformation PATH
     * from a document's raw field text to one specific matched token (walking stage by stage and
     * matching offsets — see `SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver`).
     *
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getAnalysisStages(string $text, bool $useSearchAnalyzer = false): array
    {
        if ($text === '') {
            return [];
        }

        $indexName = $this->indexNameResolver->resolve($this->config->getPageSourceIdentifier());

        try {
            $detail = $this->elasticaClient
                ->getIndex($indexName)
                ->analyze([
                    'text' => $text,
                    'analyzer' => $useSearchAnalyzer ? $this->resolveSearchAnalyzerName() : $this->resolveIndexAnalyzerName(),
                    'explain' => true,
                ]);
        } catch (ExceptionInterface) {
            return [];
        }

        return $this->mapAnalysisStages($detail);
    }

    /**
     * A branching pipeline diagram for a piece of text, laid out by STAGE rather than by lineage: one
     * "row" per analyzer stage (char filter, tokenizer, or token filter, in chain order — exactly
     * {@see getAnalysisStages()}'s own stage list), each carrying every token alive at that point, plus a
     * flat list of parent→child EDGES connecting a row's tokens to the specific tokens they produced in
     * the NEXT row. This is the same tree {@see getAnalysisStages()}'s data always implied — just
     * rank-layered (grouped by depth-from-origin) instead of nested by indentation, because depth here IS
     * "which stage produced this token": a stage's own output always re-emits even an UNCHANGED token
     * (Elasticsearch's `_analyze` response includes every token at every stage, touched or not), so no
     * edge ever needs to skip a row.
     *
     * Built entirely from the SAME `_analyze?explain=true` stages {@see getAnalysisStages()} already
     * returns — no new Elasticsearch call. Elasticsearch's own `position` field (present on every
     * tokenizer/filter token, Lucene's encoding of "which slot in the token stream this occupies") is the
     * link between consecutive stages: two tokens in neighboring stages that share the same `position`
     * are treated as parent/child. This is a correlation, not a real parent pointer (the `_analyze` API
     * exposes none) — confirmed sufficient in practice against this project's own `search_debug_synonyms`
     * (1 token in, 4 out, at two positions) and `search_debug_decompound` (1 token in, 2 out, same
     * position) filters. It can misattribute lineage only when a single stage emits more than one token
     * at the SAME position with DIFFERENT text (unresolvable from `position` alone) — accepted as a known
     * limitation rather than solved with per-filter-type special-casing. A token whose position has no
     * match in the previous row simply has no incoming edge, rather than being silently dropped.
     *
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function getAnalysisTree(string $text, bool $useSearchAnalyzer = false): array
    {
        return $this->buildLayeredTree($this->getAnalysisStages($text, $useSearchAnalyzer));
    }

    /**
     * @param array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}> $stages
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    protected function buildLayeredTree(array $stages): array
    {
        if ($stages === []) {
            return ['stages' => [], 'edges' => []];
        }

        $layeredStages = [];
        $edges = [];
        // Ids alive at the PREVIOUS stage, keyed by position — every id sharing a position with the
        // CURRENT stage's token is that token's parent (see this method's own docblock on the position
        // correlation this relies on).
        $idsByPositionInPreviousStage = [];
        // Every id that turned out to have at least one outgoing edge — {@see markRemovedTokens()} below
        // needs this to tell "this token was REMOVED here" (a stop-word/min-length filter dropped it
        // outright — no id anywhere in the next stage shares its position) apart from "this token is the
        // genuine final result" (true only for the actual last stage, handled separately by never being
        // checked at all).
        $hasOutgoingEdge = [];

        foreach ($stages as $stageIndex => $stage) {
            $nodes = [];
            $idsByPositionInThisStage = [];

            // A char filter's own pseudo-token (see wholeTextAsToken()) has no real position at all — it
            // runs before tokenization even exists, so position 0 is only ever a fallback default, not a
            // real Lucene position. The FIRST time real per-word positions appear is the tokenizer
            // stage's own output — so whenever the previous stage collapsed to exactly one token (every
            // char filter stage, always; sometimes a filter stage too, e.g. after a min-length filter
            // dropped everything but one survivor), that ONE token is the shared ancestor of every token
            // THIS stage produces, full stop, regardless of what position each of them individually ended
            // up at. Position-matching alone would only ever connect it to whichever of this stage's
            // tokens happens to land at position 0, silently dropping every other one — confirmed live: a
            // hyphenated compound tokenized into two real, differently-positioned words ("Bandscheiben",
            // "Drehstuhl") only showed a connector to the first.
            $soleParentId = $this->resolveSoleParentId($idsByPositionInPreviousStage);

            foreach ($stage['tokens'] as $tokenIndex => $token) {
                $id = $stageIndex . ':' . $tokenIndex;
                $position = $token['position'] ?? $tokenIndex;
                // `position` stays on the node through this method (stripped from the PUBLIC shape at the
                // very end, see the return statement) — {@see markRemovedTokens()} needs it to insert a
                // synthetic `∅` marker into its OWN correct left-to-right slot, not just append it after
                // every real node a row happens to already have.
                $nodes[] = ['id' => $id, 'token' => $token['token'], 'isRemoved' => false, 'position' => $position];

                $idsByPositionInThisStage[$position][] = $id;

                $parentIds = $soleParentId !== null ? [$soleParentId] : ($idsByPositionInPreviousStage[$position] ?? []);

                foreach ($parentIds as $parentId) {
                    $edges[] = ['from' => $parentId, 'to' => $id];
                    $hasOutgoingEdge[$parentId] = true;
                }
            }

            $layeredStages[] = [
                'label' => $stage['operation'],
                'definition' => $stage['definition'],
                'componentKind' => $stage['componentKind'],
                'componentName' => $stage['componentName'],
                'definitionTruncated' => $stage['definitionTruncated'],
                'isStem' => $stage['isStem'],
                'nodes' => $nodes,
            ];

            $idsByPositionInPreviousStage = $idsByPositionInThisStage;
        }

        $this->markRemovedTokens($layeredStages, $edges, $hasOutgoingEdge);

        // `position` was only ever an internal bookkeeping field for markRemovedTokens() above to sort
        // by — never part of the shape the frontend actually consumes (see this method's own return
        // type), so it's stripped here rather than carried all the way out.
        foreach ($layeredStages as &$layeredStage) {
            foreach ($layeredStage['nodes'] as &$node) {
                unset($node['position']);
            }
        }
        unset($layeredStage, $node);

        return ['stages' => $layeredStages, 'edges' => $edges];
    }

    /**
     * Whether a stage collapsed to exactly one surviving token, regardless of what position it happens to
     * carry — see {@see buildLayeredTree()}'s own docblock on why that one token is then the unconditional
     * parent of every token the NEXT stage produces.
     *
     * @param array<int, array<string>> $idsByPosition
     */
    protected function resolveSoleParentId(array $idsByPosition): ?string
    {
        if (count($idsByPosition) !== 1) {
            return null;
        }

        $idsAtOnlyPosition = reset($idsByPosition);

        return count($idsAtOnlyPosition) === 1 ? $idsAtOnlyPosition[0] : null;
    }

    /**
     * A token that simply vanishes between two consecutive stages — no id anywhere in the next stage
     * shares its position, so it has no outgoing edge at all — was REMOVED by that next stage's own
     * filter (a stop-word or min-length filter dropping it outright is the common case; a transformation
     * always produces SOME child, even a completely different string). Elasticsearch's `_analyze`
     * response has no explicit "removed" marker of its own; this is inferred purely from that absence. A
     * node in the genuinely LAST stage is never flagged — reaching the end of the pipeline alive is a
     * real final token, not a removal.
     *
     * Rendered as a synthetic "empty set" node injected into the very NEXT stage's own row, at the SAME
     * position it was removed at — i.e. spliced into that row's node list in position order alongside the
     * real survivors, not simply appended after them — linked by a normal edge, the exact same
     * connector-line code that draws every other edge handles this one too, no special case needed on the
     * rendering side. Appears exactly ONCE per dead end, not re-propagated down every remaining row: a
     * `removed:`-id node is itself skipped when deciding what to flag, since it trivially has no outgoing
     * edge of its own (it isn't a real analyzer token) and re-flagging it would otherwise chain a repeated
     * `∅` all the way to the bottom of the diagram — confirmed live, first cut of this method did exactly
     * that.
     *
     * Insertion order matters for more than cosmetics: the tree-diagram frontend centers a parent above
     * the AVERAGE position of its own children, bottom-up. A naively APPENDED marker (the first cut of
     * this method) lands after every real, still-surviving sibling in that row — so a token removed early
     * (e.g. a single stray letter dropped by an edge-ngram filter's `min_gram`) would drag its own entire
     * ancestor chain far to the right, chasing a marker sitting past a completely unrelated sibling's
     * large fan-out, rather than sitting in its own correct early slot — confirmed live on the malformed
     * description fragment `"><B>Verpackungseinheit"`: the tokenizer's own `"B"` token (this method's own
     * plan doc — see `resolveSoleParentId()` — already covers WHY that split happens) survived several
     * stages before being dropped for being too short, and its `∅` marker landing after
     * `"Verpackungseinheit"`'s own 17-way ngram fan-out pulled `"B"`'s entire lineage 2500+px to the right.
     *
     * @param array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool, position: int}>}> $layeredStages Modified in place.
     * @param array<int, array{from: string, to: string}> $edges Modified in place.
     * @param array<string, bool> $hasOutgoingEdge
     */
    protected function markRemovedTokens(array &$layeredStages, array &$edges, array $hasOutgoingEdge): void
    {
        $stageCount = count($layeredStages);

        for ($stageIndex = 0; $stageIndex < $stageCount - 1; $stageIndex++) {
            foreach ($layeredStages[$stageIndex]['nodes'] as $node) {
                if ($node['isRemoved'] || isset($hasOutgoingEdge[$node['id']])) {
                    continue;
                }

                $removedId = 'removed:' . $node['id'];
                $removedNode = ['id' => $removedId, 'token' => '∅', 'isRemoved' => true, 'position' => $node['position']];
                $this->insertNodeByPosition($layeredStages[$stageIndex + 1]['nodes'], $removedNode);
                $edges[] = ['from' => $node['id'], 'to' => $removedId];
            }
        }
    }

    /**
     * Splices $node into $nodes at the first index whose own `position` is greater — i.e. keeps $nodes in
     * ascending-position order, the same order the main stage-building loop already produces for every
     * REAL token (Elasticsearch reports a stage's tokens in stream order to begin with). Ties (an
     * existing node at the exact same position) keep their relative order — $node is inserted AFTER them
     * — since ties only ever occur between same-position SIBLINGS (a synonym/decompound fan-out), and a
     * removed marker has no real basis to claim it belongs before any of them.
     *
     * @param array<int, array{id: string, token: string, isRemoved: bool, position: int}> $nodes Modified in place.
     * @param array{id: string, token: string, isRemoved: bool, position: int} $node
     */
    protected function insertNodeByPosition(array &$nodes, array $node): void
    {
        foreach ($nodes as $index => $existingNode) {
            if ($existingNode['position'] > $node['position']) {
                array_splice($nodes, $index, 0, [$node]);

                return;
            }
        }

        $nodes[] = $node;
    }

    /**
     * Analyzer names are resolved from the LIVE index's schema (see IndexSchemaReader — the cluster's
     * merged mapping is the truth; schema JSON file locations and merge layering are
     * installation-specific), keyed off the `full-text` field. `full-text-boosted` is assumed to share
     * its analyzers — true for this schema, and a simplification the per-element analysis relies on
     * anyway, since document elements are not analyzed per-field.
     */
    protected function resolveSearchAnalyzerName(): string
    {
        return $this->resolveAnalyzerName(
            fn (SearchIndexFieldTransfer $field): ?string => $field->getSearchAnalyzerName(),
        );
    }

    protected function resolveIndexAnalyzerName(): string
    {
        return $this->resolveAnalyzerName(
            fn (SearchIndexFieldTransfer $field): ?string => $field->getAnalyzerName(),
        );
    }

    /**
     * Shared lookup behind {@see resolveSearchAnalyzerName()} and {@see resolveIndexAnalyzerName()} — the
     * two differ only in which analyzer-name getter they read off the matched `full-text` field.
     *
     * @param callable(\Generated\Shared\Transfer\SearchIndexFieldTransfer): (string|null) $getAnalyzerName
     */
    protected function resolveAnalyzerName(callable $getAnalyzerName): string
    {
        foreach ($this->indexSchemaReader->getPageIndexSchema()->getFields() as $searchIndexFieldTransfer) {
            if ($searchIndexFieldTransfer->getName() === PageIndexMap::FULL_TEXT) {
                return $getAnalyzerName($searchIndexFieldTransfer) ?? IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
            }
        }

        return IndexSchemaMapper::DEFAULT_ANALYZER_NAME;
    }

    /**
     * Both the search-time and index-time analyzers are custom (tokenizer + filter chain defined in
     * `page.json`), so Elasticsearch's `explain` response reports `custom_analyzer: true` and nests the
     * tokens under `tokenfilters[]` (one entry per filter, in chain order) rather than under a single
     * `analyzer` key, for either. The LAST filter's tokens are the fully-processed, final tokens —
     * deliberately not hardcoding which index that is (`array_key_last()` instead of e.g. `[0]`), so this
     * keeps working unmodified if a stemming filter is ever appended to either chain: the new filter would
     * simply become the new last entry, its tokens picked up automatically without a code change here.
     * (A BUILT-IN analyzer — e.g. the `standard` fallback — reports no `tokenfilters` at all; its final
     * tokens live under `analyzer.tokens`, covered by the fallback chain below.)
     *
     * @param array<string, mixed> $detail
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    protected function mapTokenDetail(array $detail): array
    {
        $tokenFilters = $detail['tokenfilters'] ?? [];
        $lastFilterKey = array_key_last($tokenFilters);

        $tokens = $lastFilterKey !== null
            ? ($tokenFilters[$lastFilterKey]['tokens'] ?? [])
            : ($detail['analyzer']['tokens'] ?? $detail['tokenizer']['tokens'] ?? []);

        return $this->mapTokens($tokens);
    }

    /**
     * Every char filter is a single whole-text transformation (no per-token offsets — char filters run
     * BEFORE tokenization, on the raw character stream), represented here as one pseudo-token spanning
     * the whole filtered text, so the caller can treat every stage — char filters, the tokenizer, and
     * every token filter — uniformly. Confirmed live against this shop's `unit_symbol_normalizer` char
     * filter (`page.json`).
     *
     * A BUILT-IN (non-custom) analyzer reports no tokenizer/tokenfilters breakdown at all — its tokens
     * live directly under `analyzer.tokens` as a single opaque stage, covered by the fallback at the end.
     *
     * Each stage also carries a `definition`: the component's own configuration (e.g.
     * "edge_ngram (min_gram: 2, max_gram: 20)"), looked up by name in the live index schema — `null`
     * when the component is a BUILT-IN Elasticsearch one used as-is (e.g. "lowercase", "standard"),
     * since there is nothing custom to show for those. `componentKind`/`componentName` are the same
     * pair `getComponentConfig()` accepts to re-fetch the FULL, untruncated config server-side — `null`
     * whenever `definition` itself is `null`, since there is then nothing to re-fetch either.
     *
     * @param array<string, mixed> $detail
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    protected function mapAnalysisStages(array $detail): array
    {
        $stages = [];

        foreach ((array)($detail['charfilters'] ?? []) as $charFilter) {
            $filteredText = (string)(($charFilter['filtered_text'] ?? [])[0] ?? '');
            $name = (string)($charFilter['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_CHAR_FILTER,
                'char filter',
                $name,
                [$this->wholeTextAsToken($filteredText)],
            );
        }

        $tokenizerTokens = $this->mapTokens($detail['tokenizer']['tokens'] ?? []);
        if ($tokenizerTokens !== []) {
            $name = (string)($detail['tokenizer']['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_TOKENIZER,
                'tokenizer',
                $name,
                $tokenizerTokens,
            );
        }

        foreach ((array)($detail['tokenfilters'] ?? []) as $tokenFilter) {
            $tokens = $this->mapTokens($tokenFilter['tokens'] ?? []);

            // Deliberately NOT skipped when $tokens is empty (a filter that removed every surviving
            // token — a stop-word list wiping out a whole one-word query is the common case): this stage
            // boundary is exactly what {@see buildLayeredTree()}'s removed-token detection needs to see.
            // Silently dropping it here would make the PREVIOUS stage look like the pipeline's genuine
            // final output, misreporting a removed token as a real final result — confirmed live: "und"
            // (a German stop word) rendered as if `german_normalization` were its last step, no `∅`
            // marker, because the very next stage (`german_stop_words`, the one that actually removed it)
            // used to be skipped here for having nothing left to show.
            $name = (string)($tokenFilter['name'] ?? '?');
            $stages[] = $this->buildStage(
                IndexSchemaMapper::COMPONENT_KIND_FILTER,
                'filter',
                $name,
                $tokens,
            );
        }

        if ($stages === [] && isset($detail['analyzer']['tokens'])) {
            $stages[] = [
                'operation' => 'analyzer: ' . (string)($detail['analyzer']['name'] ?? '?'),
                // A built-in analyzer is used by name only, never customized — nothing to look up.
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'isStem' => false,
                'tokens' => $this->mapTokens($detail['analyzer']['tokens']),
            ];
        }

        return $stages;
    }

    /**
     * @param string $componentKind One of the `IndexSchemaMapper::COMPONENT_KIND_*` constants.
     * @param string $operationLabel
     * @param string $name
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}
     */
    protected function buildStage(string $componentKind, string $operationLabel, string $name, array $tokens): array
    {
        $component = $this->indexSchemaReader->findComponent($componentKind, $name);
        $formatted = $this->componentDefinitionFormatter->format($component);

        // Surfaces a stemming filter as "stem: X" instead of the generic "filter: X" every other token
        // filter gets — this was the original trigger for this whole tree feature ("we currently don't
        // display the Stamm"), so it deliberately isn't buried as just another line among many. Heuristic
        // on the component's real ES `type` (its `filter`/`type` setting, e.g. "stemmer", "snowball",
        // "kstem") when the schema resolved one, falling back to the filter's own NAME for a built-in
        // stemmer used without a custom definition (e.g. Elasticsearch's bundled "german_stemmer"). Also
        // exposed as its own `isStem` boolean (not just baked into the `operation` string) so a renderer
        // can style a stem stage distinctly without re-parsing the label text.
        $isStem = $componentKind === IndexSchemaMapper::COMPONENT_KIND_FILTER
            && $this->looksLikeStemmer($component?->getType() ?? $name);

        if ($isStem) {
            $operationLabel = 'stem';
        }

        return [
            'operation' => $operationLabel . ': ' . $name,
            'definition' => $formatted['label'] ?? null,
            'componentKind' => $formatted !== null ? $componentKind : null,
            'componentName' => $formatted !== null ? $name : null,
            'definitionTruncated' => $formatted['truncated'] ?? false,
            'isStem' => $isStem,
            'tokens' => $tokens,
        ];
    }

    /**
     * @param string $typeOrName
     */
    protected function looksLikeStemmer(string $typeOrName): bool
    {
        return preg_match('/stemmer|snowball|kstem/i', $typeOrName) === 1;
    }

    /**
     * @param array<int, array<string, mixed>> $rawTokens
     *
     * @return array<array{token: string, startOffset: int, endOffset: int, position: int}>
     */
    protected function mapTokens(array $rawTokens): array
    {
        $result = [];
        foreach ($rawTokens as $index => $token) {
            if (!isset($token['token'], $token['start_offset'], $token['end_offset'])) {
                continue;
            }

            $result[] = [
                'token' => $token['token'],
                'startOffset' => $token['start_offset'],
                'endOffset' => $token['end_offset'],
                // Elasticsearch reports `position` on every real tokenizer/filter token (Lucene's own
                // token-stream slot index — see getAnalysisTree()'s docblock); the array index is only
                // ever the fallback for a synthetic char-filter pseudo-token, which carries none.
                'position' => (int)($token['position'] ?? $index),
            ];
        }

        return $result;
    }

    /**
     * Offsets are UTF-16 code units (see `TokenHighlighter`'s own docblock for the same Lucene
     * invariant), via the shared {@see Utf16CodeUnitConverter} — so a char-filter pseudo-token's
     * `endOffset` is directly comparable to a real token's offsets from a later stage.
     *
     * @param string $text
     *
     * @return array{token: string, startOffset: int, endOffset: int}
     */
    protected function wholeTextAsToken(string $text): array
    {
        return [
            'token' => $text,
            'startOffset' => 0,
            'endOffset' => Utf16CodeUnitConverter::lengthOf(Utf16CodeUnitConverter::toUtf16($text)),
        ];
    }
}
