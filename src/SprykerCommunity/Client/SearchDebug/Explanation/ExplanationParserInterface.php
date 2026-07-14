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
     * - Returns matched tokens (keyed by token) with their per-field weights and their effective total.
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
