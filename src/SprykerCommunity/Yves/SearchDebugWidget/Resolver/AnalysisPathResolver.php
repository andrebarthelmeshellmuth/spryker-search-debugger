<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;

class AnalysisPathResolver implements AnalysisPathResolverInterface
{
    /**
     * @var \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface
     */
    protected SearchDebugClientInterface $searchDebugClient;

    /**
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     */
    public function __construct(SearchDebugClientInterface $searchDebugClient)
    {
        $this->searchDebugClient = $searchDebugClient;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $text
     * @param string $token
     * @param int $startOffset
     * @param int $endOffset
     *
     * @return array<int, array{text: string, operation: string|null}>|null
     */
    public function resolve(string $text, string $token, int $startOffset, int $endOffset): ?array
    {
        $stages = $this->searchDebugClient->getTextAnalysisStages($text);

        if ($stages === []) {
            return null;
        }

        // Matching by offset ALONE is not enough to anchor the starting point: an edge-ngram filter
        // reports every prefix of a word at the SAME whole-word offset (e.g. "ca", "cab", "cable" and
        // "cables" all span the same range for the word "Cables") — the token TEXT is what actually
        // picks out the one we were asked for among same-offset siblings.
        $lastStageIndex = array_key_last($stages);
        $currentToken = $this->findToken($stages[$lastStageIndex]['tokens'], $token, $startOffset, $endOffset);

        if ($currentToken === null) {
            return null;
        }

        $path = [['text' => $currentToken['token'], 'operation' => null]];

        for ($stageIndex = $lastStageIndex; $stageIndex > 0; $stageIndex--) {
            $parentToken = $this->findContainingToken(
                $stages[$stageIndex - 1]['tokens'],
                $currentToken['startOffset'],
                $currentToken['endOffset'],
            );

            if ($parentToken === null) {
                // Genuinely shouldn't happen (offsets are a Lucene invariant across every stage), but a
                // partial path is still useful diagnostic information — stop rather than fail the whole page.
                break;
            }

            // $path[0] is the entry we already resolved for $currentToken — the stage we're looking at
            // right now (stages[$stageIndex]) is exactly the operation that produced it from $parentToken.
            $path[0]['operation'] = $stages[$stageIndex]['operation'];

            array_unshift($path, ['text' => $parentToken['token'], 'operation' => null]);

            $currentToken = $parentToken;
        }

        return $path;
    }

    /**
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     * @param string $token
     * @param int $startOffset
     * @param int $endOffset
     *
     * @return array{token: string, startOffset: int, endOffset: int}|null
     */
    protected function findToken(array $tokens, string $token, int $startOffset, int $endOffset): ?array
    {
        foreach ($tokens as $candidate) {
            if (
                $candidate['token'] === $token
                && $candidate['startOffset'] === $startOffset
                && $candidate['endOffset'] === $endOffset
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Picks the token whose range fully contains [$childStartOffset, $childEndOffset) — the TIGHTEST
     * such match, in the unlikely case more than one candidate contains it (defensive tie-break only; a
     * well-formed filter chain shouldn't produce that ambiguity).
     *
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     * @param int $childStartOffset
     * @param int $childEndOffset
     *
     * @return array{token: string, startOffset: int, endOffset: int}|null
     */
    protected function findContainingToken(array $tokens, int $childStartOffset, int $childEndOffset): ?array
    {
        $bestMatch = null;
        $bestSpan = null;

        foreach ($tokens as $token) {
            if ($token['startOffset'] > $childStartOffset || $token['endOffset'] < $childEndOffset) {
                continue;
            }

            $span = $token['endOffset'] - $token['startOffset'];
            if ($bestSpan !== null && $span >= $bestSpan) {
                continue;
            }

            $bestMatch = $token;
            $bestSpan = $span;
        }

        return $bestMatch;
    }
}
