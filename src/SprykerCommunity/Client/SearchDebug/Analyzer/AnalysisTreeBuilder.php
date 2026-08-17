<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

class AnalysisTreeBuilder implements AnalysisTreeBuilderInterface
{
    /**
     * @param array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}> $stages
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function build(array $stages): array
    {
        if ($stages === []) {
            return ['stages' => [], 'edges' => []];
        }

        [$layeredStages, $edges, $hasOutgoingEdge] = $this->buildLayers($stages);

        [$layeredStages, $edges] = $this->markRemovedTokens($layeredStages, $edges, $hasOutgoingEdge);

        // `position` was only ever an internal bookkeeping field for markRemovedTokens() above to sort
        // by — never part of the shape the frontend actually consumes (see this method's own return
        // type), so it's stripped here rather than carried all the way out.
        $publicStages = [];
        foreach ($layeredStages as $layeredStage) {
            $publicStages[] = [
                'label' => $layeredStage['label'],
                'definition' => $layeredStage['definition'],
                'componentKind' => $layeredStage['componentKind'],
                'componentName' => $layeredStage['componentName'],
                'definitionTruncated' => $layeredStage['definitionTruncated'],
                'isStem' => $layeredStage['isStem'],
                'nodes' => array_map(
                    static fn (array $node): array => ['id' => $node['id'], 'token' => $node['token'], 'isRemoved' => $node['isRemoved']],
                    $layeredStage['nodes'],
                ),
            ];
        }

        return ['stages' => $publicStages, 'edges' => $edges];
    }

    /**
     * @param array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}> $stages
     *
     * @return array{
     *     0: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool, position: int}>}>,
     *     1: array<int, array{from: string, to: string}>,
     *     2: array<string, bool>,
     * }
     */
    protected function buildLayers(array $stages): array
    {
        $layeredStages = [];
        $edges = [];
        // Candidates alive at the PREVIOUS stage, keyed by position — every candidate sharing a position
        // with the CURRENT stage's token is a possible parent (see this class's own docblock, and
        // {@see resolveParentIds()}, on the position correlation this relies on and its one known gap).
        $candidatesByPositionInPreviousStage = [];
        // Every id that turned out to have at least one outgoing edge — {@see markRemovedTokens()} below
        // needs this to tell "this token was REMOVED here" (a stop-word/min-length filter dropped it
        // outright — no id anywhere in the next stage shares its position) apart from "this token is the
        // genuine final result" (true only for the actual last stage, handled separately by never being
        // checked at all).
        $hasOutgoingEdge = [];

        foreach ($stages as $stageIndex => $stage) {
            $nodes = [];
            $candidatesByPositionInThisStage = [];

            // A char filter's own pseudo-token (see SearchStringAnalyzer::wholeTextAsToken()) has no real
            // position at all — it runs before tokenization even exists, so position 0 is only ever a
            // fallback default, not a real Lucene position. The FIRST time real per-word positions appear
            // is the tokenizer stage's own output — so whenever the previous stage collapsed to exactly
            // one token (every char filter stage, always; sometimes a filter stage too, e.g. after a
            // min-length filter dropped everything but one survivor), that ONE token is the shared
            // ancestor of every token THIS stage produces, full stop, regardless of what position each of
            // them individually ended up at. Position-matching alone would only ever connect it to
            // whichever of this stage's tokens happens to land at position 0, silently dropping every
            // other one — confirmed live: a hyphenated compound tokenized into two real,
            // differently-positioned words ("Bandscheiben", "Drehstuhl") only showed a connector to the
            // first.
            $soleParentId = $this->resolveSoleParentId($candidatesByPositionInPreviousStage);

            foreach ($stage['tokens'] as $tokenIndex => $token) {
                $id = $stageIndex . ':' . $tokenIndex;
                $position = $token['position'];
                // `position` stays on the node through this class (stripped from the PUBLIC shape at the
                // very end, see build()) — {@see markRemovedTokens()} needs it to insert a synthetic `∅`
                // marker into its OWN correct left-to-right slot, not just append it after every real node
                // a row happens to already have.
                $nodes[] = ['id' => $id, 'token' => $token['token'], 'isRemoved' => false, 'position' => $position];

                $candidatesByPositionInThisStage[$position][] = ['id' => $id, 'token' => $token['token']];

                $parentIds = $soleParentId !== null
                    ? [$soleParentId]
                    : $this->resolveParentIds($candidatesByPositionInPreviousStage[$position] ?? [], $token['token']);

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

            $candidatesByPositionInPreviousStage = $candidatesByPositionInThisStage;
        }

        return [$layeredStages, $edges, $hasOutgoingEdge];
    }

    /**
     * Whether a stage collapsed to exactly one surviving token, regardless of what position it happens to
     * carry — see {@see buildLayers()}'s own docblock on why that one token is then the unconditional
     * parent of every token the NEXT stage produces.
     *
     * @param array<int, array<array{id: string, token: string}>> $candidatesByPosition
     */
    protected function resolveSoleParentId(array $candidatesByPosition): ?string
    {
        if (count($candidatesByPosition) !== 1) {
            return null;
        }

        $candidatesAtOnlyPosition = reset($candidatesByPosition);

        return count($candidatesAtOnlyPosition) === 1 ? $candidatesAtOnlyPosition[0]['id'] : null;
    }

    /**
     * When more than one candidate shares a token's own position (a same-STAGE fan-out in the PREVIOUS
     * stage — synonym expansion, decompounding), prefer whichever candidate is PREFIX-RELATED to the
     * token — the same string, or one a prefix of the other — over connecting to every candidate. This
     * covers two distinct ways a filter's output can still be traced back to a specific same-position
     * candidate by text alone: an UNCHANGED pass-through (equal text, e.g. a stemmer leaving an
     * already-base-form word untouched: "stuhl" === "stuhl"), and an edge-ngram filter's own output,
     * which is always a PREFIX of whatever token it was cut from ("st"/"stu"/"stuh" are all prefixes of
     * "stuhl", never of an unrelated same-position sibling). Confirmed live, in two stages: a
     * decompounder's own "brennenstuhlbrand"/"stuhl" pair, both passed through a stemmer unchanged, used
     * to draw a fully-connected mesh instead of the obviously-correct 1:1 pairing (exact-equality alone
     * fixed this); immediately after that same pair, an edge-ngram filter's own shorter prefixes
     * ("st"/"stu"/"stuh") still meshed with BOTH candidates, since none of them is EQUAL to either
     * candidate — only the prefix relationship (not equality alone) resolves that second case.
     *
     * Falls back to EVERY same-position candidate — the prior, still-ambiguous behavior this class's own
     * docblock already documents as a known, accepted limitation — only when no candidate is prefix-related
     * at all (e.g. a synonym filter's genuinely unrelated replacement text), since the `_analyze` API
     * gives no better signal in that case.
     *
     * @param array<int, array{id: string, token: string}> $candidates
     * @param string $token
     *
     * @return array<string>
     */
    protected function resolveParentIds(array $candidates, string $token): array
    {
        if ($candidates === []) {
            return [];
        }

        $prefixMatches = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => str_starts_with($candidate['token'], $token) || str_starts_with($token, $candidate['token']),
        ));

        $matches = $prefixMatches !== [] ? $prefixMatches : $candidates;

        return array_map(static fn (array $candidate): string => $candidate['id'], $matches);
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
     * @param array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool, position: int}>}> $layeredStages
     * @param array<int, array{from: string, to: string}> $edges
     * @param array<string, bool> $hasOutgoingEdge
     *
     * @return array{
     *     0: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool, position: int}>}>,
     *     1: array<int, array{from: string, to: string}>,
     * }
     */
    protected function markRemovedTokens(array $layeredStages, array $edges, array $hasOutgoingEdge): array
    {
        $stageCount = count($layeredStages);

        for ($stageIndex = 0; $stageIndex < $stageCount - 1; $stageIndex++) {
            foreach ($layeredStages[$stageIndex]['nodes'] as $node) {
                if ($node['isRemoved'] || isset($hasOutgoingEdge[$node['id']])) {
                    continue;
                }

                $removedId = 'removed:' . $node['id'];
                $removedNode = ['id' => $removedId, 'token' => '∅', 'isRemoved' => true, 'position' => $node['position']];
                $nextStage = $layeredStages[$stageIndex + 1];
                $nextStageNodes = $nextStage['nodes'];
                $this->insertNodeByPosition($nextStageNodes, $removedNode);
                $nextStage['nodes'] = $nextStageNodes;
                $layeredStages[$stageIndex + 1] = $nextStage;
                $edges[] = ['from' => $node['id'], 'to' => $removedId];
            }
        }

        return [$layeredStages, $edges];
    }

    /**
     * Splices $node into $nodes at the first index whose own `position` is greater — i.e. keeps $nodes in
     * ascending-position order, the same order the main stage-building loop already produces for every
     * REAL token (Elasticsearch reports a stage's tokens in stream order to begin with). Ties (an
     * existing node at the exact same position) keep their relative order — $node is inserted AFTER them
     * — since ties only ever occur between same-position SIBLINGS (a synonym/decompound fan-out), and a
     * removed marker has no real basis to claim it belongs before any of them.
     *
     * @phpstan-param array<int, array{id: string, token: string, isRemoved: bool, position: int}> $nodes
     * @phpstan-param array{id: string, token: string, isRemoved: bool, position: int} $node
     *
     * @param array<int, array{id: string, token: string, isRemoved: bool, position: int}> $nodes Modified in place.
     * @param array{id: string, token: string, isRemoved: bool, position: int}|array $node
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
}
