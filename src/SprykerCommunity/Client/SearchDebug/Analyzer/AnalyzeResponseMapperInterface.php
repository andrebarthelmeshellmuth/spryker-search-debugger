<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

interface AnalyzeResponseMapperInterface
{
    /**
     * @param array<string, mixed> $detail
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function mapTokenDetail(array $detail): array;

    /**
     * @param array<string> $texts
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokens
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>|null Null when
     *   a rebased offset falls outside its own text's bounds — the gap assumption the caller documents
     *   didn't hold for this response, and the caller should fall back to one call per text.
     */
    public function rebaseTokensByText(array $texts, array $tokens): ?array;
}
