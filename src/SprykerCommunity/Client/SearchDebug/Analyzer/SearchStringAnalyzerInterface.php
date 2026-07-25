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
     * @param string $text
     * @param bool $useSearchAnalyzer See {@see getTokenOffsets()}'s parameter of the same name.
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getAnalysisStages(string $text, bool $useSearchAnalyzer = false): array;
}
