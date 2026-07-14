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
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTokenOffsets(string $text): array;
}
