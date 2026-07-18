<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Explanation;

interface ExplanationParserInterface
{
    /**
     * Specification:
     * - Reduces an Elasticsearch `_explanation` tree to the score contributions of the given query tokens.
     * - Returns matched tokens (keyed by token) with their effective total and the field that total is
     *   primarily attributed to — combined across a term's per-field weights via whichever combination the
     *   explain tree's own nodes actually used (max/dis_max or sum/bool-should), detected from the node
     *   descriptions rather than assumed. Also returns that same winning field's BM25 boost/idf/tf
     *   breakdown when the explain tree was shaped as a recognizable BM25Similarity leaf, null otherwise.
     * - Returns `function_score` boost-function contributions (field_value_factor, decay functions,
     *   script_score, ...) as their own "score functions" bucket, distinct from other contributions.
     * - Returns every other scoring node verbatim as an "other contribution", so unrecognized query
     *   shapes degrade gracefully instead of being dropped or mislabeled as token matches.
     *
     * @param array<string, mixed> $explanation
     * @param array<string> $queryTokens
     *
     * @return array<string, mixed>
     */
    public function parse(array $explanation, array $queryTokens): array;
}
