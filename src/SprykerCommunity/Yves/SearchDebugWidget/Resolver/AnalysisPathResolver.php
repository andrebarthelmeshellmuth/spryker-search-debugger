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
     * @return array<int, array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool}>|null
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

        $path = [$this->originPathEntry($currentToken['token'])];

        for ($stageIndex = $lastStageIndex; $stageIndex > 0; $stageIndex--) {
            $parentToken = $this->findContainingToken(
                $stages[$stageIndex - 1]['tokens'],
                $currentToken['token'],
                $currentToken['startOffset'],
                $currentToken['endOffset'],
            );

            if ($parentToken === null) {
                // Genuinely shouldn't happen (offsets are a Lucene invariant across every stage), but a
                // partial path is still useful diagnostic information — stop rather than fail the whole page.
                break;
            }

            // $path[0] is the entry we already resolved for $currentToken — the stage we're looking at
            // right now (stages[$stageIndex]) is exactly the operation (and its definition) that produced
            // it from $parentToken.
            $path[0]['operation'] = $stages[$stageIndex]['operation'];
            $path[0]['definition'] = $stages[$stageIndex]['definition'];
            $path[0]['componentKind'] = $stages[$stageIndex]['componentKind'];
            $path[0]['componentName'] = $stages[$stageIndex]['componentName'];
            $path[0]['definitionTruncated'] = $stages[$stageIndex]['definitionTruncated'];

            array_unshift($path, $this->originPathEntry($parentToken['token']));

            $currentToken = $parentToken;
        }

        return $path;
    }

    /**
     * A freshly unshifted/seeded path entry — nothing has produced it FROM an earlier stage yet, so
     * every "how did we get here" field starts null/false, to potentially be filled in one loop
     * iteration later.
     *
     * @param string $text
     *
     * @return array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool}
     */
    protected function originPathEntry(string $text): array
    {
        return [
            'text' => $text,
            'operation' => null,
            'definition' => null,
            'componentKind' => null,
            'componentName' => null,
            'definitionTruncated' => false,
        ];
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
     * Picks the token whose range fully contains [$childStartOffset, $childEndOffset). Prefers an
     * IDENTICAL-TEXT candidate over the tightest-span heuristic when one exists — offset containment
     * alone is not enough once a synonym filter is in the chain: a synonym-injected token (e.g. "button")
     * shares the EXACT SAME offset as the word that triggered it (e.g. "switch") through every later
     * pass-through stage (word_delimiter, stop, length, ...), so without a text preference, the
     * tightest-span tie-break (arbitrary "first one wins" for equal spans) can silently swap the lineage
     * onto the WRONG same-offset sibling at the very first backward step and never recover — the
     * displayed path would then show "switch" persisting through every stage and "button" appearing out
     * of nowhere at the final one, attributing the transformation to the wrong filter entirely. Confirmed
     * live against a real product description matching the synonym pair "switch, button".
     *
     * Falls back to the tightest-span match when no exact-text candidate exists — still correct for
     * genuine transformations (lowercasing, edge-ngram truncation, decompounding) where the child's text
     * legitimately differs from its parent's.
     *
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     * @param string $childText
     * @param int $childStartOffset
     * @param int $childEndOffset
     *
     * @return array{token: string, startOffset: int, endOffset: int}|null
     */
    protected function findContainingToken(array $tokens, string $childText, int $childStartOffset, int $childEndOffset): ?array
    {
        $bestMatch = null;
        $bestSpan = null;

        foreach ($tokens as $token) {
            if ($token['startOffset'] > $childStartOffset || $token['endOffset'] < $childEndOffset) {
                continue;
            }

            if ($token['token'] === $childText) {
                return $token;
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
