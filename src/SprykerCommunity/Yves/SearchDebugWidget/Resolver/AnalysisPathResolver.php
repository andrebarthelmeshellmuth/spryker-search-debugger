<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class AnalysisPathResolver implements AnalysisPathResolverInterface
{
    /**
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     * @param \SprykerCommunity\Yves\SearchDebugWidget\Resolver\TokenHighlighterInterface $tokenHighlighter
     */
    public function __construct(protected SearchDebugClientInterface $searchDebugClient, protected TokenHighlighterInterface $tokenHighlighter)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $text
     * @param string $token
     * @param int $startOffset
     * @param int $endOffset
     * @param bool $useSearchAnalyzer
     *
     * @return array<int, array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool}>|null
     */
    public function resolve(string $text, string $token, int $startOffset, int $endOffset, bool $useSearchAnalyzer = false): ?array
    {
        $stages = $this->searchDebugClient->getTextAnalysisStages($text, $useSearchAnalyzer);

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

        // Whether $currentToken (by the time the loop below ends) is stages[0]'s own matched token — either
        // because there was only ever one stage to begin with, or because every backward step found a
        // containing parent all the way down. Only then is it safe to attribute stages[0]'s OWN operation
        // (see below) and to prepend the true, pre-pipeline raw input as the path's real origin.
        $tracedToFirstStage = $lastStageIndex === 0;

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

            if ($stageIndex === 1) {
                // $currentToken (before it's overwritten below) is the last stage-1 token — the specific
                // sub-span of $parentToken's OWN text that continues into the rest of the path. Highlighting
                // it in the origin box mirrors the token-source page's `<mark>` treatment: "this part of the
                // text is what led here" — see addOriginHighlight() for why this is validated, not assumed.
                $this->addOriginHighlight($path[0], $currentToken);
                $tracedToFirstStage = true;
            }

            $currentToken = $parentToken;
        }

        // Without this, stages[0]'s own operation (a char filter, or the tokenizer itself when the
        // analyzer has no char filters) is never attributed to anything — the loop above only ever labels
        // an entry with the stage that comes AFTER it, so whatever produced $path[0] at this point has no
        // label yet, and $text itself (the actual raw, pre-pipeline input) never appears on the page at
        // all: $path[0]'s text is already stages[0]'s OUTPUT, which a char filter can have rewritten (e.g.
        // this shop's own "& => and"), silently passing off transformed text as if it were the origin.
        if ($tracedToFirstStage) {
            $path[0]['operation'] = $stages[0]['operation'];
            $path[0]['definition'] = $stages[0]['definition'];
            $path[0]['componentKind'] = $stages[0]['componentKind'];
            $path[0]['componentName'] = $stages[0]['componentName'];
            $path[0]['definitionTruncated'] = $stages[0]['definitionTruncated'];

            $rawOrigin = $this->originPathEntry($text);
            $this->addOriginHighlight($rawOrigin, $currentToken);
            array_unshift($path, $rawOrigin);
        }

        return $path;
    }

    /**
     * Elasticsearch's `_analyze` API reports tokenizer/filter-stage offsets relative to the ORIGINAL
     * input text (Lucene's `correctOffset()` contract) — but a char-filter stage's own synthetic
     * whole-text token (see `SearchStringAnalyzer::wholeTextAsToken()`) is offset [0, length) relative to
     * THAT FILTER'S OWN output text instead. The two coordinate systems are identical only when nothing
     * upstream of the origin changed the text's length (the common case) — a length-changing char filter
     * (e.g. this shop's own `& => and`) would make them diverge for any text containing the affected
     * character. Rather than trying to re-derive the real mapping, this slices the origin text at the
     * child token's offsets and only trusts the result when it VALIDATES: the slice must equal the
     * child's own known-correct text exactly. A mismatch means the coordinate spaces diverged for this
     * particular text — the origin entry is then left unhighlighted (today's behavior), never shown with
     * a wrong or misaligned mark.
     *
     * @phpstan-param array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, highlightedHtml: string|null} $originEntry
     * @phpstan-param array{token: string, startOffset: int, endOffset: int} $childToken
     *
     * @param array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, highlightedHtml: string|null}|array $originEntry
     * @param array{token: string, startOffset: int, endOffset: int}|array $childToken
     */
    protected function addOriginHighlight(array &$originEntry, array $childToken): void
    {
        $slice = $this->sliceCodeUnits($originEntry['text'], $childToken['startOffset'], $childToken['endOffset']);

        if ($slice !== $childToken['token']) {
            return;
        }

        $originEntry['highlightedHtml'] = $this->tokenHighlighter->highlight($originEntry['text'], [
            ['startOffset' => $childToken['startOffset'], 'endOffset' => $childToken['endOffset']],
        ]);
    }

    /**
     * Same UTF-16-code-unit slicing {@see TokenHighlighter} uses internally (Lucene offsets are Java
     * string indices, not Unicode code points), via the shared {@see Utf16CodeUnitConverter} — this
     * method only adds the clamping, a VALIDATION concern specific to this resolver.
     *
     * @param string $text
     * @param int $startCodeUnit
     * @param int $endCodeUnit
     */
    protected function sliceCodeUnits(string $text, int $startCodeUnit, int $endCodeUnit): string
    {
        $textUtf16 = Utf16CodeUnitConverter::toUtf16($text);
        $lengthInCodeUnits = Utf16CodeUnitConverter::lengthOf($textUtf16);

        $startCodeUnit = max(0, min($startCodeUnit, $lengthInCodeUnits));
        $endCodeUnit = max($startCodeUnit, min($endCodeUnit, $lengthInCodeUnits));

        return Utf16CodeUnitConverter::slice($textUtf16, $startCodeUnit, $endCodeUnit);
    }

    /**
     * A freshly unshifted/seeded path entry — nothing has produced it FROM an earlier stage yet, so
     * every "how did we get here" field starts null/false, to potentially be filled in later (either by
     * the loop's next iteration, or — for stages[0]'s own entry and the true raw-input entry prepended
     * ahead of it — by the code directly after the loop in {@see resolve()}). `highlightedHtml` starts
     * null too: it is only ever filled in by {@see addOriginHighlight()}, called once for the entry that
     * traces stages[0]'s token within its OWN parent's text (inside the loop, when `$stageIndex === 1`),
     * and once more for the true raw-input entry tracing stages[0]'s token within `$text` (after the
     * loop). A path that `break`s early (an earlier stage's containing token wasn't found) never reaches
     * either call, so its first entry correctly stays unhighlighted.
     *
     * @param string $text
     *
     * @return array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, highlightedHtml: string|null}
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
            'highlightedHtml' => null,
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
     * When no exact-text candidate exists either, prefers a candidate whose text the child is a genuine
     * PREFIX of over the plain tightest-span tie-break — this is the edge-ngram case: an ngram filter's
     * generated tokens all carry their ORIGINAL (pre-truncation) token's offsets, so a truncated child
     * (e.g. "handcar", 7 of "handcart"'s 8 characters) has the exact same span as every one of its
     * same-offset synonym siblings (e.g. "trolley", also span 7 — spans reflect the ORIGINAL query
     * text's word length, not either candidate's own text length). Without this tier, the tightest-span
     * fallback ties and arbitrarily keeps whichever sibling the synonym filter emitted first — reproduced
     * live against the real synonym pair "trolley, handcart": walking back from ngram-truncated
     * "handcar" landed on "trolley" at every earlier stage, making the path show the synonym swap
     * happening at the ngram filter instead of at the actual `fulltext_synonyms` stage, and losing the
     * final two characters of the real synonym term in the process.
     *
     * Falls back to the plain tightest-span match when neither an exact nor a prefix candidate exists —
     * still correct for genuine transformations (lowercasing, decompounding) where the child's text
     * legitimately differs from its parent's without one containing the other.
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
        $bestPrefixMatch = null;
        $bestPrefixSpan = null;

        foreach ($tokens as $token) {
            if (!$this->containsChildSpan($token, $childStartOffset, $childEndOffset)) {
                continue;
            }

            if ($token['token'] === $childText) {
                return $token;
            }

            $span = $token['endOffset'] - $token['startOffset'];

            if (str_starts_with($token['token'], $childText)) {
                if ($this->isTighterSpan($bestPrefixSpan, $span)) {
                    $bestPrefixMatch = $token;
                    $bestPrefixSpan = $span;
                }

                continue;
            }

            if (!$this->isTighterSpan($bestSpan, $span)) {
                continue;
            }

            $bestMatch = $token;
            $bestSpan = $span;
        }

        return $bestPrefixMatch ?? $bestMatch;
    }

    /**
     * Whether $token's own span fully covers [$childStartOffset, $childEndOffset) — the offset-containment
     * precondition {@see findContainingToken()} requires of ANY candidate before text is even considered.
     *
     * @phpstan-param array{token: string, startOffset: int, endOffset: int} $token
     *
     * @param array{token: string, startOffset: int, endOffset: int}|array $token
     * @param int $childStartOffset
     * @param int $childEndOffset
     */
    protected function containsChildSpan(array $token, int $childStartOffset, int $childEndOffset): bool
    {
        return $token['startOffset'] <= $childStartOffset && $token['endOffset'] >= $childEndOffset;
    }

    /**
     * Whether $candidateSpan should replace the best span tracked so far for one of
     * {@see findContainingToken()}'s two candidate tiers (exact-prefix or plain tightest-span) — null
     * means nothing has been tracked yet, so anything qualifies. Shared by both tiers: they differ only in
     * WHICH candidates are eligible (prefix-of-child-text vs. everything else), not in how "tighter" is
     * decided once a candidate reaches the comparison.
     *
     * @param int|null $currentBestSpan
     * @param int $candidateSpan
     */
    protected function isTighterSpan(?int $currentBestSpan, int $candidateSpan): bool
    {
        return $currentBestSpan === null || $candidateSpan < $currentBestSpan;
    }
}
