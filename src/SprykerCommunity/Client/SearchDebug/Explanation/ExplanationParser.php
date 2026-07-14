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
     * Every Lucene weight node starts with this, including shapes this parser cannot attribute to a
     * single field:term pair (e.g. "weight(Synonym(full-text:a full-text:b) in 12)").
     *
     * @var string
     */
    protected const WEIGHT_NODE_PREFIX = 'weight(';

    /**
     * @var string
     */
    public const KEY_MATCHED_TOKENS = 'matchedTokens';

    /**
     * @var string
     */
    public const KEY_OTHER_CONTRIBUTIONS = 'otherContributions';

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

        $this->walkNode($explanation, $terms, $otherContributions);

        return $this->splitByQueryTokens($terms, $otherContributions, $queryTokens);
    }

    /**
     * Walks the recursive `_explanation` tree by node shape:
     * - A weight node attributable to one `field:term` pair is collected as a term contribution
     *   (descent stops there: its children are TF/IDF internals, which are multiplicative factors of
     *   this node's value, not additive score parts of their own).
     * - Any other weight node is kept verbatim rather than descended into, for the same reason — its
     *   internals would otherwise surface as bogus standalone contributions.
     * - Any other node with children is descended into.
     * - Any other scoring leaf is kept verbatim, so unknown query shapes (e.g. a future function_score)
     *   degrade gracefully instead of being dropped.
     *
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $terms
     * @param array<int, array<string, mixed>> $otherContributions
     *
     * @return void
     */
    protected function walkNode(array $node, array &$terms, array &$otherContributions): void
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
                $this->addTermWeight($terms, $matches['term'], $matches['field'], $value);

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
            foreach ($details as $childNode) {
                $this->walkNode($childNode, $terms, $otherContributions);
            }

            return;
        }

        if ($description === '') {
            return;
        }

        $otherContributions[] = [
            'description' => $description,
            'value' => $value,
        ];
    }

    /**
     * The effective per-term value is the MAX over its field weights, not the sum: a `best_fields`
     * multi_match combines per-field scores under a dis_max ("max of:") node, so only the best-scoring
     * field actually contributes to `_score`. `field` records which one that was, so the UI can show
     * where a term's contribution came from; the non-winning fields' own values are deliberately NOT
     * part of the output — they contribute nothing to the score, and the UI shows contributing parts
     * only. The per-field map exists only locally, to find the max.
     *
     * @param array<string, array<string, mixed>> $terms
     * @param string $term
     * @param string $field
     * @param float $value
     *
     * @return void
     */
    protected function addTermWeight(array &$terms, string $term, string $field, float $value): void
    {
        $fields = $terms[$term]['fieldWeights'] ?? [];
        $fields[$field] = max($fields[$field] ?? 0.0, $value);

        $total = max($fields);

        $terms[$term] = [
            'total' => $total,
            'field' => array_search($total, $fields, true),
            'fieldWeights' => $fields,
        ];
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
            if (isset($queryTokenSet[$term])) {
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
