<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface TokenHighlighterInterface
{
    /**
     * Returns the full $text, HTML-escaped, with every span in $matches wrapped in a `<mark>` tag.
     *
     * @param string $text
     * @param array<array{startOffset: int, endOffset: int}> $matches
     *
     * @return string
     */
    public function highlight(string $text, array $matches): string;
}
