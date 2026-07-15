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
     * @return array<int, array{text: string, operation: string|null}>|null Null when $token isn't found
     *   at that offset in the last analysis stage at all (e.g. stale offsets from a re-indexed document).
     *   The first entry's `operation` is always null (it's the origin, nothing produced it).
     */
    public function resolve(string $text, string $token, int $startOffset, int $endOffset): ?array;
}
