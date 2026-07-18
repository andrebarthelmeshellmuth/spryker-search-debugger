<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Explanation;

class ExplanationParser implements ExplanationParserInterface
{
    /**
     * Matches Lucene term-weight explanation descriptions, e.g.
     * "weight(full-text-boosted:hamm in 42) [PerFieldSimilarity], result of:".
     *
     * @var string
     */
    protected const TERM_WEIGHT_PATTERN = '/^weight\((?<field>[^:\s()]+):(?<term>.+?) in \d+\)/';

    /**
     * Matches a Lucene SYNONYM weight node — one query position expanded into several alternative terms
     * by a synonym filter, e.g. "weight(Synonym(full-text:button full-text:switch) in 12803)". Confirmed
     * live: this is a SINGLE node with ONE combined value for the whole group, not one node per term — a
     * synonym filter genuinely cannot be scored as independent per-term contributions, since Lucene
     * matched "either of these, at this one position", not each term separately. `terms` captures the
     * raw space-separated `field:term` pairs, however many the group has (a synonym rule can list any
     * number of equivalent words) — parsed by {@see addSynonymWeight()}.
     *
     * @var string
     */
    protected const SYNONYM_WEIGHT_PATTERN = '/^weight\(Synonym\((?<terms>.+?)\) in \d+\)/';

    /**
     * Every Lucene weight node starts with this, including shapes neither {@see TERM_WEIGHT_PATTERN} nor
     * {@see SYNONYM_WEIGHT_PATTERN} recognizes.
     *
     * @var string
     */
    protected const WEIGHT_NODE_PREFIX = 'weight(';

    /**
     * The combined-term display key joins a synonym group's terms with this, e.g. "button, switch" — the
     * SAME separator {@see splitByQueryTokens()} splits back on to check membership against the user's
     * actual query tokens. Also matches the separator {@see \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatter}
     * already uses to join a config list preview, for a consistent "these are a group" visual convention
     * across the debug tool.
     *
     * @var string
     */
    protected const SYNONYM_TERM_SEPARATOR = ', ';

    /**
     * Matches a `function_score` boost-function leaf's own description — the documented Lucene/ES
     * phrasing for `field_value_factor`, decay functions (`gauss`/`exp`/`linear`), and `script_score`.
     * Confirmed live against a real `script_score` function_score (the search-ranking business-signal
     * query): its node matches this pattern but arrives WITH details, so it is handled by the dedicated
     * script-score branch in {@see walkNode()} instead; this leaf pattern remains the net for the other
     * function types and detail-less shapes. If real explain output ever uses different wording, the
     * worst case is these nodes fall through to the generic {@see KEY_OTHER_CONTRIBUTIONS} bucket
     * instead of {@see KEY_SCORE_FUNCTIONS} — never dropped.
     *
     * @var string
     */
    protected const SCORE_FUNCTION_PATTERN = '/^(field value function|function score|script score function|gauss|exp|linear)\b/i';

    /**
     * A `script score function` node as produced by a real function_score query (confirmed live against
     * the search-ranking business-signal query, ES 7 / boost_mode "replace"):
     *
     *   sum of: <- V (final _score)
     *   ├─ min of: <- V
     *   │ ├─ script score function, ...: <- V, THIS node
     *   │ │ └─ _score: <- S, the wrapped query's own relevance score
     *   │ │ └─ (the original query tree) <- the familiar term-weight breakdown
     *   │ └─ maxBoost <- 3.4028235E38 (float-max sentinel, pure noise)
     *   └─ match on required clause (0) <- filtered by the zero check
     *
     * @var string
     */
    protected const SCRIPT_SCORE_NODE_PREFIX = 'script score function';

    /**
     * The child of a script-score node carrying the WRAPPED query's own relevance score — the value the
     * painless script saw as `_score`. Matched by prefix: ES renders it as "_score: " (trailing space).
     *
     * @var string
     */
    protected const QUERY_SCORE_NODE_PREFIX = '_score';

    /**
     * A function_score explain always caps the function result with a `min of: [function, maxBoost]`
     * node whose maxBoost leaf is FLT_MAX (3.4028235E38) unless a max_boost was configured — a sentinel,
     * not a score contribution; surfacing it as one would show a nonsensical gigantic number.
     *
     * @var string
     */
    protected const MAX_BOOST_DESCRIPTION = 'maxBoost';

    /**
     * Matches the description of a node that combines its children by taking the MAXIMUM (Lucene
     * `DisjunctionMaxQuery`, i.e. a `dis_max`/`best_fields` multi_match) — e.g. "max of:" or
     * "max plus 0.1 times others of:" for a non-zero tie_breaker.
     *
     * @var string
     */
    protected const COMBINE_PATTERN_MAX = '/^max\b.*of:$/i';

    /**
     * Matches the description of a node that combines its children by SUMMING them (Lucene `BooleanQuery`
     * "should" clauses, i.e. a `most_fields`/`cross_fields` multi_match without full field blending) —
     * e.g. "sum of:".
     *
     * @var string
     */
    protected const COMBINE_PATTERN_SUM = '/^sum\b.*of:$/i';

    /**
     * @var string
     */
    protected const COMBINE_MODE_MAX = 'max';

    /**
     * @var string
     */
    protected const COMBINE_MODE_SUM = 'sum';

    /**
     * @var string
     */
    public const KEY_MATCHED_TOKENS = 'matchedTokens';

    /**
     * @var string
     */
    public const KEY_OTHER_CONTRIBUTIONS = 'otherContributions';

    /**
     * @var string
     */
    public const KEY_SCORE_FUNCTIONS = 'scoreFunctions';

    /**
     * The wrapped query's own relevance score (float) when a function_score script wrapped the query,
     * null otherwise — i.e. the pure text-match subtotal BEFORE any score function was applied. The
     * matched-token contributions always add up against THIS number, not the final `_score`.
     *
     * @var string
     */
    public const KEY_QUERY_SCORE = 'queryScore';

    /**
     * A `weight(field:term)` leaf's own single child under BM25Similarity — e.g.
     * "score(freq=8.0), computed as boost * idf * tf from:". Matched by substring rather than a full
     * pattern anchored at the start: the leading "score(freq=X.0), " portion is otherwise-unused detail
     * this class doesn't need to capture.
     *
     * @var string
     */
    protected const BM25_BREAKDOWN_MARKER = 'computed as boost * idf * tf from:';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF = 'idf';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF = 'tf';

    /**
     * `boost` alone has no trailing comma/explanation (unlike idf/tf and their own children below), so it
     * is matched as an exact description rather than a prefix.
     *
     * @var string
     */
    protected const BM25_CHILD_DESCRIPTION_BOOST = 'boost';

    /**
     * idf's own two children — `n,`/`N,` (lowercase = documents containing the term, uppercase = total
     * documents with the field at all) — matched by prefix since each carries its own trailing
     * explanation (e.g. "n, number of documents containing term").
     *
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF_N = 'n,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF_CAPITAL_N = 'N,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_FREQ = 'freq';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_K1 = 'k1';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_B = 'b,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_DL = 'dl';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_AVGDL = 'avgdl';

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $explanation
     * @param array<string> $queryTokens
     *
     * @return array<string, mixed>
     */
    public function parse(array $explanation, array $queryTokens): array
    {
        $terms = [];
        $otherContributions = [];
        $scoreFunctions = [];
        $queryScore = null;

        $this->walkNode($explanation, $terms, $otherContributions, $scoreFunctions, $queryScore);

        $result = $this->splitByQueryTokens($terms, $otherContributions, $queryTokens);
        $result[static::KEY_SCORE_FUNCTIONS] = $scoreFunctions;
        $result[static::KEY_QUERY_SCORE] = $queryScore;

        return $result;
    }

    /**
     * Walks the recursive `_explanation` tree by node shape:
     * - A weight node attributable to one `field:term` pair is collected as a term contribution
     *   (descent stops there: its children are TF/IDF internals, which are multiplicative factors of
     *   this node's value, not additive score parts of their own). Per-field weights for the SAME term
     *   are combined using whichever mode ($combineMode) the nearest enclosing combiner node's own
     *   description indicated — max/dis_max or sum/bool-should — not assumed.
     * - Any other weight node is kept verbatim rather than descended into, for the same reason — its
     *   internals would otherwise surface as bogus standalone contributions.
     * - A node with children updates $combineMode from its OWN description (falling back to the mode
     *   inherited from its ancestor when its description doesn't match a known combiner shape) and is
     *   descended into.
     * - A `function_score` boost-function leaf is collected separately from other contributions.
     * - Any other scoring leaf is kept verbatim, so unknown query shapes degrade gracefully instead of
     *   being dropped.
     *
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $terms
     * @param array<int, array<string, mixed>> $otherContributions
     * @param array<int, array<string, mixed>> $scoreFunctions
     * @param float|null $queryScore
     * @param string|null $combineMode
     *
     * @return void
     */
    protected function walkNode(
        array $node,
        array &$terms,
        array &$otherContributions,
        array &$scoreFunctions,
        ?float &$queryScore,
        ?string $combineMode = null,
    ): void {
        $value = (float)($node['value'] ?? 0.0);

        if ($value === 0.0) {
            // A zero-valued node contributes nothing to its parent's combined score, even when its own
            // children report non-zero numbers deeper down. Filter-context clauses (e.g. active facet
            // filters) are a common case: Lucene explains them internally for transparency but excludes
            // them from scoring — e.g. "match on required clause, product of: # clause (0) * *:* (1)"
            // reports 0 at every ancestor level despite the literal "1" leaf inside it. Stopping at the
            // first zero avoids surfacing that inner "1" as a fake contribution.
            return;
        }

        $description = (string)($node['description'] ?? '');

        if ($description === static::MAX_BOOST_DESCRIPTION) {
            return;
        }

        if (str_starts_with($description, static::SCRIPT_SCORE_NODE_PREFIX)) {
            $this->addScriptScoreNode($node, $value, $description, $terms, $otherContributions, $scoreFunctions, $queryScore);

            return;
        }

        if (str_starts_with($description, static::WEIGHT_NODE_PREFIX)) {
            if (preg_match(static::TERM_WEIGHT_PATTERN, $description, $matches)) {
                $this->addTermWeight($terms, $matches['term'], $matches['field'], $value, $combineMode ?? static::COMBINE_MODE_MAX, $this->extractBm25Breakdown($node));

                return;
            }

            if (preg_match(static::SYNONYM_WEIGHT_PATTERN, $description, $matches)) {
                $this->addSynonymWeight($terms, $matches['terms'], $value, $combineMode ?? static::COMBINE_MODE_MAX, $this->extractBm25Breakdown($node));

                return;
            }

            $otherContributions[] = [
                'description' => $description,
                'value' => $value,
            ];

            return;
        }

        $details = $node['details'] ?? [];

        if ($details !== []) {
            if ($this->tryAddMaxOfDistinctTermsWeight($description, $details, $value, $terms)) {
                return;
            }

            // A node's OWN description is only trusted as a combine-mode signal when it actually
            // combines more than one child — summing or maxing a SINGLE value produces that same value
            // either way, so a single-child node reveals nothing about how things combine; it must pass
            // the INHERITED mode through unchanged instead of overriding it with its own (structurally
            // present but semantically meaningless here) "sum of:"/"max of:" wording.
            //
            // Confirmed live: a synonym-expanded term wraps EACH field's weight in its own single-child
            // "sum of:" node (`weight(Synonym(...) in N)` is its only child) — plain, non-synonym term
            // matches have no such wrapper at all. Without this check, that inner "sum of:" incorrectly
            // overrode the REAL governing combiner (an ancestor "max of:" node combining the two fields'
            // weights via dis_max), making a synonym group's per-field weights get SUMMED instead of
            // MAX'd — inflating its matched-token total past the document's actual `_score`.
            $childCombineMode = count($details) > 1
                ? ($this->detectCombineMode($description) ?? $combineMode)
                : $combineMode;

            foreach ($details as $childNode) {
                $this->walkNode($childNode, $terms, $otherContributions, $scoreFunctions, $queryScore, $childCombineMode);
            }

            return;
        }

        if ($description === '') {
            return;
        }

        if (preg_match(static::SCORE_FUNCTION_PATTERN, $description) === 1) {
            $scoreFunctions[] = [
                'description' => $description,
                'value' => $value,
            ];

            return;
        }

        $otherContributions[] = [
            'description' => $description,
            'value' => $value,
        ];
    }

    /**
     * Handles a `script score function` node WITH children — the shape a real function_score script
     * produces (see {@see SCRIPT_SCORE_NODE_PREFIX} for the confirmed tree). Three things happen:
     * - The node itself is recorded as a score function (its value IS the function's result).
     * - Its `_score:` child's value is captured as {@see KEY_QUERY_SCORE} — the wrapped query's own
     *   relevance score, which the matched-token breakdown adds up against.
     * - The walk continues INTO the `_score:` child, so the familiar term-weight breakdown of the
     *   wrapped query is parsed exactly as if no function_score existed.
     * Children other than `_score:` (none observed live; defensive) get the normal walk too.
     *
     * @param array<string, mixed> $node
     * @param float $value
     * @param string $description
     * @param array<string, array<string, mixed>> $terms
     * @param array<int, array<string, mixed>> $otherContributions
     * @param array<int, array<string, mixed>> $scoreFunctions
     * @param float|null $queryScore
     *
     * @return void
     */
    protected function addScriptScoreNode(
        array $node,
        float $value,
        string $description,
        array &$terms,
        array &$otherContributions,
        array &$scoreFunctions,
        ?float &$queryScore,
    ): void {
        $scoreFunctions[] = [
            'description' => $description,
            'value' => $value,
        ];

        foreach ($node['details'] ?? [] as $childNode) {
            $childDescription = (string)($childNode['description'] ?? '');

            if ($queryScore === null && str_starts_with($childDescription, static::QUERY_SCORE_NODE_PREFIX)) {
                $queryScore = (float)($childNode['value'] ?? 0.0);
            }

            $this->walkNode($childNode, $terms, $otherContributions, $scoreFunctions, $queryScore);
        }
    }

    /**
     * @param string $description
     *
     * @return string|null One of {@see COMBINE_MODE_MAX}/{@see COMBINE_MODE_SUM}, or null when
     *   $description doesn't match a known combiner shape (the caller then keeps the inherited mode).
     */
    protected function detectCombineMode(string $description): ?string
    {
        if (preg_match(static::COMBINE_PATTERN_SUM, $description) === 1) {
            return static::COMBINE_MODE_SUM;
        }

        if (preg_match(static::COMBINE_PATTERN_MAX, $description) === 1) {
            return static::COMBINE_MODE_MAX;
        }

        return null;
    }

    /**
     * A SECOND, structurally different way a synonym group shows up in the explain tree — confirmed live
     * against this shop's real `cross_fields` multi_match (the actual production query type; a bare
     * `best_fields` multi_match produces the `Synonym(...)` node {@see addSynonymWeight()} handles
     * instead). Under `cross_fields`, Lucene does NOT wrap synonym alternatives in one combined node at
     * all — it scores "switch" and "button" as two entirely separate, independently-attributable
     * `weight(field:term)` leaves, and combines them with an ordinary "max of:" node, EXACTLY the same
     * shape a real term's per-FIELD weights get combined with (e.g. "brenne" scored on both `full-text`
     * and `full-text-boosted`). The tree alone cannot tell these two cases apart by shape — only by
     * whether the leaves share the SAME term text (a per-field combine: let normal recursion handle it,
     * each leaf calling {@see addTermWeight()} for the identical key) or DIFFERENT term text (synonym
     * alternatives for one query position: must be combined into ONE key here, using the value Lucene
     * itself already computed as the max, instead of letting each leaf self-report in full and
     * double-count the position).
     *
     * Returns false (handled nothing) for every node this doesn't apply to: not a "max of:" node, has
     * only one child (a real dis_max always compares 2+ alternatives), or any child isn't a directly
     * attributable term-weight leaf (a nested wrapper needs the normal recursive walk instead) — the
     * caller falls through to normal recursion in every one of those cases.
     *
     * @param string $description
     * @param array<int, array<string, mixed>> $details
     * @param float $value
     * @param array<string, array<string, mixed>> $terms
     *
     * @return bool
     */
    protected function tryAddMaxOfDistinctTermsWeight(string $description, array $details, float $value, array &$terms): bool
    {
        if (count($details) < 2 || preg_match(static::COMBINE_PATTERN_MAX, $description) !== 1) {
            return false;
        }

        $leaves = $this->flattenMaxOfTermWeightLeaves($details);

        if ($leaves === null) {
            return false;
        }

        $termNames = [];
        $firstField = null;
        $winningField = null;
        $winningChild = null;

        foreach ($leaves as $leaf) {
            $firstField ??= $leaf['field'];
            $termNames[] = $leaf['term'];

            // The group's own reported $value is exactly one leaf's value (Lucene picked the max) —
            // that SAME leaf is the one whose own field AND boost/idf/tf breakdown actually explain
            // $value; a losing leaf's field must never be shown alongside the winner's numbers.
            if ($winningChild !== null || !((float)($leaf['node']['value'] ?? 0.0) === $value)) {
                continue;
            }

            $winningChild = $leaf['node'];
            $winningField = $leaf['field'];
        }

        $uniqueTermNames = array_unique($termNames);

        if (count($uniqueTermNames) < 2) {
            // Every leaf shares the SAME term (only the field differs) — an ordinary per-field dis_max,
            // not a synonym-alternatives position. Let normal recursion handle it: each leaf calling
            // addTermWeight() for the identical key already combines them correctly.
            return false;
        }

        sort($uniqueTermNames);
        // Falls back to the first leaf's field only in the (so-far unobserved) case where no leaf's own
        // value exactly equals the group's — still shows a field either way, rather than an empty string.
        $field = $winningField ?? $firstField;
        $breakdown = $winningChild !== null ? $this->extractBm25Breakdown($winningChild) : null;
        $this->addTermWeight($terms, implode(static::SYNONYM_TERM_SEPARATOR, $uniqueTermNames), (string)$field, $value, static::COMBINE_MODE_MAX, $breakdown);

        return true;
    }

    /**
     * Recursively resolves a "max of:" node's children down to the flat set of term-weight leaves it
     * ultimately disjoins between. A child is either a direct `weight(field:term)` leaf, OR another
     * nested "max of:" node — e.g. a multi-field query with a synonym at one position produces an OUTER
     * "max of:" choosing between an INNER per-field "max of:" for each synonym alternative, not one flat
     * level. Max-of-maxes propagates the true winning leaf's value up through every level unchanged, so
     * {@see tryAddMaxOfDistinctTermsWeight()} can keep comparing against the OUTERMOST group's $value
     * regardless of how deep the winning leaf actually sits.
     *
     * Returns null (bail out, caller falls through to normal recursion) the moment ANY child isn't
     * resolvable this way — a single non-term-weight, non-"max of:" child means this isn't a pure
     * disjunction-of-term-weights tree at all, and guessing at a partial flattening would risk silently
     * mislabeling or dropping part of the real explain tree.
     *
     * @param array<int, array<string, mixed>> $details
     *
     * @return array<int, array{node: array<string, mixed>, field: string, term: string}>|null
     */
    protected function flattenMaxOfTermWeightLeaves(array $details): ?array
    {
        $leaves = [];

        foreach ($details as $childNode) {
            $childDescription = (string)($childNode['description'] ?? '');

            if (preg_match(static::TERM_WEIGHT_PATTERN, $childDescription, $matches)) {
                $leaves[] = ['node' => $childNode, 'field' => $matches['field'], 'term' => $matches['term']];

                continue;
            }

            $grandchildren = $childNode['details'] ?? [];

            if (count($grandchildren) < 2 || preg_match(static::COMBINE_PATTERN_MAX, $childDescription) !== 1) {
                return null;
            }

            $nestedLeaves = $this->flattenMaxOfTermWeightLeaves($grandchildren);

            if ($nestedLeaves === null) {
                return null;
            }

            array_push($leaves, ...$nestedLeaves);
        }

        return $leaves;
    }

    /**
     * The effective per-term value combines its per-field weights via $combineMode: MAX for a `dis_max`
     * combiner (only the best-scoring field actually contributes to `_score`), SUM for a `bool`-should
     * combiner (every matching field genuinely adds to `_score`). $combineMode is detected from the
     * explain tree's own node descriptions by the caller ({@see walkNode}), defaulting to MAX only when no
     * combiner node was seen at all.
     *
     * Confirmed live against a real basic shop's explain output: the top-level bool query combines
     * the multi_match's weight with the internal `type:product_abstract` filter clause via a literal
     * "sum of:" node (the filter clause is zero-valued and gets skipped before reaching this method, so
     * that outer sum is otherwise invisible here) — so $combineMode is `sum`, NOT the `max` this class
     * assumed before this generalization existed. For a term matching only ONE field (the common case),
     * sum and max compute the identical number, so this was never visibly wrong; it becomes observable
     * only for a term matching BOTH `full-text` and `full-text-boosted` on the same document, which this
     * live check did not happen to exercise — the detection mechanism handles that case correctly either
     * way, which is the actual point of not hardcoding a mode.
     *
     * `field` records the SINGLE LARGEST individual field weight either way: for MAX mode that field's
     * weight equals `total` (it's literally the winner); for SUM mode it's a "primary contributor" hint
     * only — `total` is the true sum across all fields, `field` does not claim to be the sole source of it.
     * The per-field map exists only locally, to compute both.
     *
     * @phpstan-param array{boost: float, idf: array{value: float, n: float, capitalN: float}, tf: array{value: float, freq: float, k1: float, b: float, dl: float, avgdl: float}}|null $breakdown
     *
     * @param array<string, array<string, mixed>> $terms
     * @param string $term
     * @param string $field
     * @param float $value
     * @param string $combineMode
     * @param array<string, mixed>|null $breakdown
     *   The BM25 boost/idf/tf breakdown behind THIS specific field weight (see {@see extractBm25Breakdown()}),
     *   null when the node wasn't shaped as a recognizable BM25 leaf. Stored per field alongside
     *   `fieldWeights` — only the primary (winning) field's breakdown is ever surfaced downstream (see
     *   {@see splitByQueryTokens()}), matching `field`'s own existing "single largest individual field
     *   weight" contract; a losing field's breakdown is simply never looked at again.
     *
     * @return void
     */
    protected function addTermWeight(array &$terms, string $term, string $field, float $value, string $combineMode, ?array $breakdown = null): void
    {
        $fields = $terms[$term]['fieldWeights'] ?? [];
        $fieldBreakdowns = $terms[$term]['fieldBreakdowns'] ?? [];

        $fields[$field] = $combineMode === static::COMBINE_MODE_SUM
            ? ($fields[$field] ?? 0.0) + $value
            : max($fields[$field] ?? 0.0, $value);

        if ($breakdown !== null) {
            $fieldBreakdowns[$field] = $breakdown;
        }

        $total = $combineMode === static::COMBINE_MODE_SUM ? array_sum($fields) : max($fields);
        $primaryField = array_search(max($fields), $fields, true);

        $terms[$term] = [
            'total' => $total,
            'field' => $primaryField,
            'fieldWeights' => $fields,
            'fieldBreakdowns' => $fieldBreakdowns,
        ];
    }

    /**
     * Extracts the boost/idf/tf breakdown from a `weight(field:term)` leaf's own child — the standard
     * BM25Similarity explain shape confirmed live against this shop's real query:
     *
     *   weight(full-text:handcart in 7007) [PerFieldSimilarity], result of:
     *   └─ score(freq=8.0), computed as boost * idf * tf from:
     *      ├─ boost <- exact-match leaf
     *      ├─ idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from: <- has its own n/N children
     *      │ ├─ n, number of documents containing term
     *      │ └─ N, total number of documents with field
     *      └─ tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from: <- own children
     *         ├─ freq, occurrences of term within document
     *         ├─ k1, term saturation parameter
     *         ├─ b, length normalization parameter
     *         ├─ dl, length of field (approximate)
     *         └─ avgdl, average length of field
     *
     * Returns null when the node isn't shaped this way at all (missing child, wrong wording, or a
     * different Similarity module entirely) — no partial or guessed breakdown is ever shown, matching
     * this class's existing degrade-gracefully posture for every other unrecognized shape.
     *
     * @param array<string, mixed> $weightNode
     *
     * @return array{boost: float, idf: array{value: float, n: float, capitalN: float}, tf: array{value: float, freq: float, k1: float, b: float, dl: float, avgdl: float}}|null
     */
    protected function extractBm25Breakdown(array $weightNode): ?array
    {
        $scoreNode = ($weightNode['details'] ?? [])[0] ?? null;

        if ($scoreNode === null || !str_contains((string)($scoreNode['description'] ?? ''), static::BM25_BREAKDOWN_MARKER)) {
            return null;
        }

        $scoreDetails = $scoreNode['details'] ?? [];
        $boostNode = $this->findChildByDescription($scoreDetails, static::BM25_CHILD_DESCRIPTION_BOOST);
        $idfNode = $this->findChildByPrefix($scoreDetails, static::BM25_CHILD_PREFIX_IDF);
        $tfNode = $this->findChildByPrefix($scoreDetails, static::BM25_CHILD_PREFIX_TF);

        if ($boostNode === null || $idfNode === null || $tfNode === null) {
            return null;
        }

        $idfDetails = $idfNode['details'] ?? [];
        $tfDetails = $tfNode['details'] ?? [];

        $n = $this->findChildByPrefix($idfDetails, static::BM25_CHILD_PREFIX_IDF_N);
        $capitalN = $this->findChildByPrefix($idfDetails, static::BM25_CHILD_PREFIX_IDF_CAPITAL_N);
        $freq = $this->findChildByPrefix($tfDetails, static::BM25_CHILD_PREFIX_TF_FREQ);
        $k1 = $this->findChildByPrefix($tfDetails, static::BM25_CHILD_PREFIX_TF_K1);
        $b = $this->findChildByPrefix($tfDetails, static::BM25_CHILD_PREFIX_TF_B);
        $dl = $this->findChildByPrefix($tfDetails, static::BM25_CHILD_PREFIX_TF_DL);
        $avgdl = $this->findChildByPrefix($tfDetails, static::BM25_CHILD_PREFIX_TF_AVGDL);

        if ($n === null || $capitalN === null || $freq === null || $k1 === null || $b === null || $dl === null || $avgdl === null) {
            return null;
        }

        return [
            'boost' => (float)($boostNode['value'] ?? 0.0),
            'idf' => [
                'value' => (float)($idfNode['value'] ?? 0.0),
                'n' => (float)($n['value'] ?? 0.0),
                'capitalN' => (float)($capitalN['value'] ?? 0.0),
            ],
            'tf' => [
                'value' => (float)($tfNode['value'] ?? 0.0),
                'freq' => (float)($freq['value'] ?? 0.0),
                'k1' => (float)($k1['value'] ?? 0.0),
                'b' => (float)($b['value'] ?? 0.0),
                'dl' => (float)($dl['value'] ?? 0.0),
                'avgdl' => (float)($avgdl['value'] ?? 0.0),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param string $description
     *
     * @return array<string, mixed>|null
     */
    protected function findChildByDescription(array $nodes, string $description): ?array
    {
        return $this->findChild($nodes, fn (string $childDescription): bool => $childDescription === $description);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param string $prefix
     *
     * @return array<string, mixed>|null
     */
    protected function findChildByPrefix(array $nodes, string $prefix): ?array
    {
        return $this->findChild($nodes, fn (string $childDescription): bool => str_starts_with($childDescription, $prefix));
    }

    /**
     * Shared linear search behind {@see findChildByDescription()} and {@see findChildByPrefix()} — the two
     * differ only in how a child's own description is matched, not in how the list is walked.
     *
     * @param array<int, array<string, mixed>> $nodes
     * @param callable(string): bool $matches
     *
     * @return array<string, mixed>|null
     */
    protected function findChild(array $nodes, callable $matches): ?array
    {
        foreach ($nodes as $node) {
            if ($matches((string)($node['description'] ?? ''))) {
                return $node;
            }
        }

        return null;
    }

    /**
     * A synonym group's terms all share the SAME field within one `Synonym(...)` node (multi_match
     * generates one clause per field, and the synonym alternatives are combined WITHIN that field's
     * clause) — so `$field` is read from any one pair, not accumulated. `$rawFieldTermPairs` is however
     * many space-separated `field:term` pairs the group has (2, 3, 5, ...; never hardcoded to 2) — sorted
     * and joined into one stable display key, e.g. "button, switch", so the SAME group always renders
     * identically regardless of the order Lucene happened to list its terms in.
     *
     * Delegates to {@see addTermWeight()} with that combined key as the "term" — the exact same
     * per-field MAX/SUM combining a real single term already gets, reused unchanged rather than
     * duplicated: a synonym group scored via `full-text` AND `full-text-boosted` (as this shop's real
     * config does) needs the identical combine-mode logic, just keyed by the group instead of one word.
     *
     * @phpstan-param array{boost: float, idf: array{value: float, n: float, capitalN: float}, tf: array{value: float, freq: float, k1: float, b: float, dl: float, avgdl: float}}|null $breakdown
     *
     * @param array<string, array<string, mixed>> $terms
     * @param string $rawFieldTermPairs
     * @param float $value
     * @param string $combineMode
     * @param array<string, mixed>|null $breakdown
     *   Extracted from the SAME `weight(Synonym(...))` node the group's own $value came from — this shape
     *   hasn't been confirmed live (this shop's real query produces the OTHER synonym shape,
     *   {@see tryAddMaxOfDistinctTermsWeight()}, instead), so null here is the expected common case, not
     *   a failure; {@see extractBm25Breakdown()} degrades to null on its own if the shape doesn't match.
     *
     * @return void
     */
    protected function addSynonymWeight(array &$terms, string $rawFieldTermPairs, float $value, string $combineMode, ?array $breakdown = null): void
    {
        $field = '';
        $termNames = [];

        foreach (explode(' ', $rawFieldTermPairs) as $pair) {
            $colonPosition = strpos($pair, ':');

            if ($colonPosition === false) {
                continue;
            }

            $field = substr($pair, 0, $colonPosition);
            $termNames[] = substr($pair, $colonPosition + 1);
        }

        if ($termNames === []) {
            return;
        }

        sort($termNames);

        $this->addTermWeight($terms, implode(static::SYNONYM_TERM_SEPARATOR, $termNames), $field, $value, $combineMode, $breakdown);
    }

    /**
     * Splits collected term weights into the user's actual query tokens and everything else.
     *
     * The explain tree contains `weight(field:term)` nodes that are not part of the search string — the
     * internal `type:product_abstract` filter clause every catalog query includes is structurally
     * indistinguishable from a real term match. Those belong under "other contributions": dropping them
     * would show a score without a visible source, and keeping them under "matched tokens" would claim
     * the user searched for something they did not.
     *
     * `array_flip()` builds the lookup set deliberately: PHP coerces numeric-string array keys to int
     * (so a term like "8845" is stored as int 8845 by `addTermWeight()`), and `isset()` applies the same
     * coercion to the lookup key — whereas a strict `in_array()` against the string token list would not
     * match, silently demoting every numeric query token to an "other contribution".
     *
     * A combined synonym-group key (e.g. "button, switch", from {@see addSynonymWeight()}) is never
     * itself one of the user's query tokens — it's split back into its constituent terms and counted as
     * matched when ANY of them is a real query token (they all originate from the SAME synonym expansion
     * of the query, so in practice either all of a group's terms are query tokens or none are).
     *
     * @param array<string, array<string, mixed>> $terms
     * @param array<int, array<string, mixed>> $otherContributions
     * @param array<string> $queryTokens
     *
     * @return array<string, mixed>
     */
    protected function splitByQueryTokens(array $terms, array $otherContributions, array $queryTokens): array
    {
        $queryTokenSet = array_flip($queryTokens);
        $matchedTokens = [];

        foreach ($terms as $term => $termInfo) {
            $termNames = str_contains((string)$term, static::SYNONYM_TERM_SEPARATOR)
                ? explode(static::SYNONYM_TERM_SEPARATOR, (string)$term)
                : [(string)$term];

            $isMatchedQueryToken = false;
            foreach ($termNames as $termName) {
                if (isset($queryTokenSet[$termName])) {
                    $isMatchedQueryToken = true;

                    break;
                }
            }

            if ($isMatchedQueryToken) {
                // fieldWeights is accumulation state for addTermWeight(), not part of the output
                // contract: only the winning field contributes to the score (dis_max), so only
                // `field`/`total` carry displayable information.
                $matchedTokens[$term] = [
                    'total' => $termInfo['total'],
                    'field' => $termInfo['field'],
                ];

                // `breakdown` (the winning field's own boost/idf/tf numbers) is only ever ADDED, never
                // set to null — a different Similarity module, or a synonym shape the extractor hasn't
                // been confirmed against live, simply leaves this key absent rather than present-but-null,
                // so callers can keep using a plain `is not empty`/`isset` check either way.
                $breakdown = $termInfo['fieldBreakdowns'][$termInfo['field']] ?? null;

                if ($breakdown !== null) {
                    $matchedTokens[$term]['breakdown'] = $breakdown;
                }

                continue;
            }

            $otherContributions[] = [
                'description' => sprintf('%s:%s', $termInfo['field'], $term),
                'value' => $termInfo['total'],
            ];
        }

        return [
            static::KEY_MATCHED_TOKENS => $matchedTokens,
            static::KEY_OTHER_CONTRIBUTIONS => $otherContributions,
        ];
    }
}
