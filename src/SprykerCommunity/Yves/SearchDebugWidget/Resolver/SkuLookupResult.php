<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

/**
 * The outcome of a "why isn't this SKU showing up" lookup — exactly one of three shapes, see
 * {@see SkuLookupResolverInterface::resolve()}'s docblock for what distinguishes them.
 */
class SkuLookupResult
{
    /**
     * The SKU does not resolve to any product at all — almost always a typo.
     *
     * @var string
     */
    public const STATUS_NOT_FOUND = 'not_found';

    /**
     * The SKU resolves to a real product, but that product has no document in this store/locale's search
     * index (unpublished, inactive, never exported, or assigned to a different store) — a dead end for
     * the word-level analysis tree, since there is no indexed content to analyze.
     *
     * @var string
     */
    public const STATUS_NOT_INDEXED = 'not_indexed';

    /**
     * The SKU resolves AND has a search document — the real case this feature exists for, whether or not
     * it happens to also be present in the CURRENT result set (see $rankPosition).
     *
     * @var string
     */
    public const STATUS_FOUND = 'found';

    /**
     * @param string $status One of the STATUS_* constants above.
     * @param string|null $productSku Null only for STATUS_NOT_FOUND.
     * @param string|null $productTitle Null only for STATUS_NOT_FOUND.
     * @param int|null $idProductAbstract Null only for STATUS_NOT_FOUND.
     * @param int|null $rankPosition 1-based position in the CURRENT search result set — only ever set for
     *   STATUS_FOUND, and even then only when the product was located within the scanned window (see
     *   $rankPositionBeyondScanWindow). Null for STATUS_NOT_FOUND/STATUS_NOT_INDEXED, and for a
     *   STATUS_FOUND product that isn't in the current result set at all.
     * @param bool $rankPositionBeyondScanWindow True when the product was searched for but not located
     *   within the scan window — distinguishes "definitely not in the result set" (false, $rankPosition
     *   null) from "might be further back than we checked" (true, $rankPosition null).
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $productSku,
        public readonly ?string $productTitle,
        public readonly ?int $idProductAbstract,
        public readonly ?int $rankPosition,
        public readonly bool $rankPositionBeyondScanWindow,
    ) {
    }
}
