<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Explanation;

/**
 * Recognizes ONE specific `_explanation` tree shape: under a `cross_fields` multi_match, Lucene does NOT
 * wrap synonym alternatives in one combined node — it scores each alternative (e.g. "switch" and
 * "button") as a separate, independently-attributable `weight(field:term)` leaf, and combines them with
 * an ordinary "max of:" node, EXACTLY the same shape a real term's per-FIELD weights get combined with
 * (e.g. one term scored on both `full-text` and `full-text-boosted`). The tree alone cannot tell these two
 * cases apart by shape — only by whether the leaves share the SAME term text (a per-field combine: let
 * {@see ExplanationParser} recurse normally, each leaf calling its own term-weight handling for the
 * identical key) or DIFFERENT term text (synonym alternatives for one query position: must be combined
 * into ONE key, using the value Lucene itself already computed as the max, instead of letting each leaf
 * self-report in full and double-count the position).
 *
 * A pure matcher: no side effects, no mutation of caller state. Returns the resolved combined term (and
 * the winning leaf, for the caller to extract its own BM25 breakdown from) or null the moment the shape
 * doesn't apply — the caller falls through to normal recursion in every null case.
 */
class CrossFieldsSynonymMatcher
{
    /**
     * Confirmed live against a real basic shop's `cross_fields` multi_match query.
     *
     * @param string $description
     * @param array<int, array<string, mixed>> $details
     * @param float $value
     *
     * @return array{term: string, field: string, winningNode: array<string, mixed>|null}|null
     */
    public function match(string $description, array $details, float $value): ?array
    {
        if (count($details) < 2 || preg_match(ExplanationParser::COMBINE_PATTERN_MAX, $description) !== 1) {
            return null;
        }

        $leaves = $this->flattenMaxOfTermWeightLeaves($details);

        if ($leaves === null) {
            return null;
        }

        $termNames = [];
        $firstField = null;
        $winningField = null;
        $winningNode = null;

        foreach ($leaves as $leaf) {
            $firstField ??= $leaf['field'];
            $termNames[] = $leaf['term'];

            // The group's own reported $value is exactly one leaf's value (Lucene picked the max) —
            // that SAME leaf is the one whose own field AND boost/idf/tf breakdown actually explain
            // $value; a losing leaf's field must never be shown alongside the winner's numbers.
            if ($winningNode !== null || !((float)($leaf['node']['value'] ?? 0.0) === $value)) {
                continue;
            }

            $winningNode = $leaf['node'];
            $winningField = $leaf['field'];
        }

        $uniqueTermNames = array_unique($termNames);

        if (count($uniqueTermNames) < 2) {
            // Every leaf shares the SAME term (only the field differs) — an ordinary per-field dis_max,
            // not a synonym-alternatives position. Let the caller's normal recursion handle it.
            return null;
        }

        sort($uniqueTermNames);

        return [
            'term' => implode(ExplanationParser::SYNONYM_TERM_SEPARATOR, $uniqueTermNames),
            // Falls back to the first leaf's field only in the (so-far unobserved) case where no leaf's
            // own value exactly equals the group's — still a real field either way, never an empty string.
            'field' => (string)($winningField ?? $firstField),
            'winningNode' => $winningNode,
        ];
    }

    /**
     * Recursively resolves a "max of:" node's children down to the flat set of term-weight leaves it
     * ultimately disjoins between. A child is either a direct `weight(field:term)` leaf, OR another nested
     * "max of:" node — e.g. a multi-field query with a synonym at one position produces an OUTER "max of:"
     * choosing between an INNER per-field "max of:" for each synonym alternative, not one flat level.
     * Max-of-maxes propagates the true winning leaf's value up through every level unchanged, so
     * {@see match()} can keep comparing against the OUTERMOST group's $value regardless of how deep the
     * winning leaf actually sits.
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

            if (preg_match(ExplanationParser::TERM_WEIGHT_PATTERN, $childDescription, $matches)) {
                $leaves[] = ['node' => $childNode, 'field' => $matches['field'], 'term' => $matches['term']];

                continue;
            }

            $grandchildren = $childNode['details'] ?? [];

            if (count($grandchildren) < 2 || preg_match(ExplanationParser::COMBINE_PATTERN_MAX, $childDescription) !== 1) {
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
}
