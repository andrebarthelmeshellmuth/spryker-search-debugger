<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface AnalysisPathResolverInterface
{
    /**
     * Reconstructs the transformation PATH from $text (as indexed) to the specific matched $token at
     * ($startOffset, $endOffset) in the LAST analysis stage — a linear walk backward through every
     * stage (char filters, tokenizer, token filters), picking at each step the one earlier-stage token
     * whose offset range contains the current one. Never branches: only the ONE lineage leading to the
     * target token is followed, regardless of how many sibling tokens an earlier stage's token produced
     * (e.g. an ngram or decompounding filter fanning one token into several) — deliberate, so the
     * result stays a path even for filter chains that fan out.
     *
     * $token is required IN ADDITION to the offsets, not redundant with them: an edge-ngram filter
     * reports every prefix of one word at the SAME whole-word offset (e.g. "ca", "cab", "cable" and
     * "cables" all span the same range for the word "Cables"), so offset alone cannot tell which of
     * those same-span siblings is the one actually being asked about.
     *
     * @param string $text
     * @param string $token
     * @param int $startOffset
     * @param int $endOffset
     *
     * @return array<int, array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool}>|null
     *   Null when $token isn't found at that offset in the last analysis stage at all (e.g. stale offsets
     *   from a re-indexed document). The first entry's `operation`/`definition`/`componentKind`/
     *   `componentName` are always null and `definitionTruncated` always false (it's the origin, nothing
     *   produced it). `definition` is the operation's own configuration (e.g.
     *   "edge_ngram (min_gram: 2, max_gram: 20)"), or null when it's a built-in Elasticsearch component
     *   used by name only, with nothing custom configured for it. `componentKind`/`componentName` are
     *   only non-null alongside a non-null `definition`, and identify which component to pass to
     *   `SearchDebugClientInterface::getComponentConfig()` for its FULL, untruncated config — worth doing
     *   only when `definitionTruncated` is true (i.e. `definition` itself left something out).
     */
    public function resolve(string $text, string $token, int $startOffset, int $endOffset): ?array;
}
