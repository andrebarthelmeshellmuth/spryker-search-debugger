<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

/**
 * One row of the token-source page: how ONE raw indexed document element (or, for a source with no
 * matching element at all, one summary placeholder) attributes to the searched token.
 *
 * A named object rather than a plain array so a field can never again silently drift out of sync between
 * production code and its tests the way `isUnattributed` once did here — every constructor call must
 * state every field explicitly, so the compiler (not a human remembering to grep the test suite) is what
 * catches a call site that needs updating. Public readonly properties, not getters: Twig's attribute
 * resolution checks a public property before any method, so `row.matched`/`row.isUnattributed`/etc. in
 * `token-source.twig` keep working exactly as they did when `$row` was an array — no template changes.
 */
class TokenSourceRow
{
    /**
     * @param array<int, string> $labelKeys
     * @param bool $matched
     * @param bool $isUnattributed True only when NOTHING named this value — not even one of the
     *   product's own attribute keys. That is the one case worth explaining in the UI (the `?` hint),
     *   because it is also the one an adopter can fix, by registering a TokenSourceProviderPlugin. A
     *   value already labeled with a real source or attribute key needs no hint.
     * @param string|null $highlightedHtml
     * @param string|null $element The raw document element text, present only when there is something to
     *   link a magnifying-glass icon to — a "no match" summary row has nothing to point at.
     * @param array<int, array{token: string, startOffset: int, endOffset: int}> $matches
     */
    public function __construct(
        public readonly array $labelKeys,
        public readonly bool $matched,
        public readonly bool $isUnattributed,
        public readonly ?string $highlightedHtml,
        public readonly ?string $element,
        public readonly array $matches,
    ) {
    }
}
