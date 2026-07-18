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
     * Returns the full $text, HTML-escaped, with every span in $matches wrapped in a `<mark>` tag. An
     * overlapping match (see {@see filterRenderable()}) is silently skipped rather than rendered.
     *
     * @param string $text
     * @param array<array{startOffset: int, endOffset: int}> $matches
     *
     * @return string
     */
    public function highlight(string $text, array $matches): string;

    /**
     * Filters $matches down to the subset {@see highlight()} will actually render as a `<mark>` — drops
     * any match that overlaps an earlier one (by startOffset). A caller that keeps its own list of
     * matches alongside the rendered HTML (e.g. one link built per rendered mark) should filter through
     * this method first and use ITS result as that list, so the two can never drift out of sync.
     *
     * Generic over the match shape (only startOffset/endOffset are ever read) so a caller whose matches
     * carry additional fields — e.g. `token` — gets them back on the surviving entries unchanged, rather
     * than this method's own type narrowing them away.
     *
     * @template T of array{startOffset: int, endOffset: int}
     *
     * @param array<T> $matches
     *
     * @return array<T>
     */
    public function filterRenderable(array $matches): array;
}
