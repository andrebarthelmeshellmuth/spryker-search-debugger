<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Explanation;

/**
 * Owns the per-term weight map {@see ExplanationParser}'s tree-walk builds up one leaf at a time, and the
 * per-field MAX/SUM combine logic behind it. A fresh instance per {@see ExplanationParser::parse()} call
 * (never constructor-injected/shared) — its whole point is accumulating state across one recursive walk,
 * and sharing one instance across multiple parse() calls (e.g. one per search-result hit) would leak one
 * product's matched terms into another's.
 */
class TermWeightAccumulator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $terms = [];

    /**
     * The effective per-term value combines its per-field weights via $combineMode: MAX for a `dis_max`
     * combiner (only the best-scoring field actually contributes to `_score`), SUM for a `bool`-should
     * combiner (every matching field genuinely adds to `_score`). $combineMode is detected from the
     * explain tree's own node descriptions by the caller ({@see ExplanationParser::walkNode()}), defaulting
     * to MAX only when no combiner node was seen at all.
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
     * @param string $term
     * @param string $field
     * @param float $value
     * @param string $combineMode
     * @param array<string, mixed>|null $breakdown
     *   The BM25 boost/idf/tf breakdown behind THIS specific field weight (see {@see Bm25BreakdownExtractor}),
     *   null when the node wasn't shaped as a recognizable BM25 leaf. Stored per field alongside
     *   `fieldWeights` — only the primary (winning) field's breakdown is ever surfaced downstream (see
     *   {@see ExplanationParser::splitByQueryTokens()}), matching `field`'s own existing "single largest
     *   individual field weight" contract; a losing field's breakdown is simply never looked at again.
     */
    public function addTerm(string $term, string $field, float $value, string $combineMode, ?array $breakdown = null): void
    {
        $fields = $this->terms[$term]['fieldWeights'] ?? [];
        $fieldBreakdowns = $this->terms[$term]['fieldBreakdowns'] ?? [];

        $fields[$field] = $combineMode === ExplanationParser::COMBINE_MODE_SUM
            ? ($fields[$field] ?? 0.0) + $value
            : max($fields[$field] ?? 0.0, $value);

        if ($breakdown !== null) {
            $fieldBreakdowns[$field] = $breakdown;
        }

        $total = $combineMode === ExplanationParser::COMBINE_MODE_SUM ? array_sum($fields) : max($fields);
        $primaryField = array_search(max($fields), $fields, true);

        $this->terms[$term] = [
            'total' => $total,
            'field' => $primaryField,
            'fieldWeights' => $fields,
            'fieldBreakdowns' => $fieldBreakdowns,
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
     * Delegates to {@see addTerm()} with that combined key as the "term" — the exact same per-field
     * MAX/SUM combining a real single term already gets, reused unchanged rather than duplicated: a
     * synonym group scored via `full-text` AND `full-text-boosted` (as a real config might) needs the
     * identical combine-mode logic, just keyed by the group instead of one word.
     *
     * @phpstan-param array{boost: float, idf: array{value: float, n: float, capitalN: float}, tf: array{value: float, freq: float, k1: float, b: float, dl: float, avgdl: float}}|null $breakdown
     *
     * @param string $rawFieldTermPairs
     * @param float $value
     * @param string $combineMode
     * @param array<string, mixed>|null $breakdown
     *   Extracted from the SAME `weight(Synonym(...))` node the group's own $value came from — this shape
     *   hasn't been confirmed live (a `cross_fields` multi_match produces the OTHER synonym shape,
     *   {@see CrossFieldsSynonymMatcher}, instead), so null here is the expected common case, not
     *   a failure; {@see Bm25BreakdownExtractor::extract()} degrades to null on its own if the shape
     *   doesn't match.
     */
    public function addSynonym(string $rawFieldTermPairs, float $value, string $combineMode, ?array $breakdown = null): void
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

        $this->addTerm(implode(ExplanationParser::SYNONYM_TERM_SEPARATOR, $termNames), $field, $value, $combineMode, $breakdown);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTerms(): array
    {
        return $this->terms;
    }
}
