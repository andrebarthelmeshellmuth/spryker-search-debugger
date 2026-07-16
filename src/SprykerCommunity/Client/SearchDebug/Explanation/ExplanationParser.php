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
     * Not yet exercised against a real function_score query (a basic shop's default catalog search
     * doesn't use one) — built ahead of that feature so the parser is ready for it. If real
     * explain output ever uses different wording, the worst case is these nodes fall through to the
     * generic {@see KEY_OTHER_CONTRIBUTIONS} bucket instead of {@see KEY_SCORE_FUNCTIONS} — never dropped.
     *
     * @var string
     */
    protected const SCORE_FUNCTION_PATTERN = '/^(field value function|function score|script score function|gauss|exp|linear)\b/i';

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

        $this->walkNode($explanation, $terms, $otherContributions, $scoreFunctions);

        $result = $this->splitByQueryTokens($terms, $otherContributions, $queryTokens);
        $result[static::KEY_SCORE_FUNCTIONS] = $scoreFunctions;

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
     * @param string|null $combineMode
     *
     * @return void
     */
    protected function walkNode(array $node, array &$terms, array &$otherContributions, array &$scoreFunctions, ?string $combineMode = null): void
    {
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

        if (str_starts_with($description, static::WEIGHT_NODE_PREFIX)) {
            if (preg_match(static::TERM_WEIGHT_PATTERN, $description, $matches)) {
                $this->addTermWeight($terms, $matches['term'], $matches['field'], $value, $combineMode ?? static::COMBINE_MODE_MAX);

                return;
            }

            if (preg_match(static::SYNONYM_WEIGHT_PATTERN, $description, $matches)) {
                $this->addSynonymWeight($terms, $matches['terms'], $value, $combineMode ?? static::COMBINE_MODE_MAX);

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
                $this->walkNode($childNode, $terms, $otherContributions, $scoreFunctions, $childCombineMode);
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
     * @param array<string, array<string, mixed>> $terms
     * @param string $term
     * @param string $field
     * @param float $value
     * @param string $combineMode
     *
     * @return void
     */
    protected function addTermWeight(array &$terms, string $term, string $field, float $value, string $combineMode): void
    {
        $fields = $terms[$term]['fieldWeights'] ?? [];
        $fields[$field] = $combineMode === static::COMBINE_MODE_SUM
            ? ($fields[$field] ?? 0.0) + $value
            : max($fields[$field] ?? 0.0, $value);

        $total = $combineMode === static::COMBINE_MODE_SUM ? array_sum($fields) : max($fields);
        $primaryField = array_search(max($fields), $fields, true);

        $terms[$term] = [
            'total' => $total,
            'field' => $primaryField,
            'fieldWeights' => $fields,
        ];
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
     * @param array<string, array<string, mixed>> $terms
     * @param string $rawFieldTermPairs
     * @param float $value
     * @param string $combineMode
     *
     * @return void
     */
    protected function addSynonymWeight(array &$terms, string $rawFieldTermPairs, float $value, string $combineMode): void
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

        $this->addTermWeight($terms, implode(static::SYNONYM_TERM_SEPARATOR, $termNames), $field, $value, $combineMode);
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
