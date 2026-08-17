<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

interface SearchStringAnalyzerInterface
{
    /**
     * @param string $searchString
     *
     * @return array<string>
     */
    public function getTokens(string $searchString): array;

    /**
     * @param string $text
     * @param bool $useSearchAnalyzer Defaults to the index-time analyzer (see the implementation's own
     *   docblock for why). Pass `true` to trace a QUERY string's own tokenization instead — e.g. to show
     *   an analysis path for one of the SRP overlay's matched query tokens, where the text in question
     *   was never indexed at all, only searched.
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTokenOffsets(string $text, bool $useSearchAnalyzer = false): array;

    /**
     * Same as {@see getTokenOffsets()}, batched into one `_analyze` call for several distinct texts.
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getTokenOffsetsForTexts(array $texts): array;

    /**
     * Index-time WORD boundaries for each of $texts — i.e. the real analyzer's own char-filter+tokenizer
     * stage, before any token filter (lowercase, stemmer, synonym, decompounder, ...) has touched
     * anything. Offsets are back into the ORIGINAL text, exactly like {@see getTokenOffsetsForTexts()},
     * but this is the raw segmentation a shopper's own text was actually split into by this index's real
     * tokenizer, not a hand-rolled approximation — the caller slices the original text by these offsets
     * to get a "word" it can safely re-analyze in isolation later and still see the true result, because
     * the boundary itself came from the real pipeline (see the implementation's own docblock for why a
     * hand-rolled boundary guess can silently produce wrong results whenever a char filter needs
     * characters on both sides of a guessed cut point).
     *
     * Falls back to the ONE available stage's tokens for a BUILT-IN (non-custom) analyzer, which reports
     * no separate tokenizer breakdown at all — the best available boundary information in that case.
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getWordSpansForTexts(array $texts): array;

    /**
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getAnalysisStages(string $text, bool $useSearchAnalyzer = false): array;

    /**
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function getAnalysisTree(string $text, bool $useSearchAnalyzer = false): array;
}
