<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Dependency\Plugin;

interface TokenSourceProviderPluginInterface
{
    /**
     * Specification:
     * - Names the product-side origin of values a project's own indexing contributes to the search
     *   document, so the token-source page can label them instead of falling back to the generic
     *   "other indexed value".
     * - Returns a map of `indexed value => list of labels`, where each label is a glossary key (or a
     *   literal string, which renders as-is when no translation exists). One value may carry several
     *   labels when genuinely several sources contributed the identical text.
     * - The value must be byte-identical to the element as it appears in the search document, because
     *   that is what it is matched against.
     * - **No tier needs to be declared.** The token-source page walks the real indexed document tier by
     *   tier, so a value is automatically attributed to whichever tier it is actually in — including
     *   both, if a project indexes it into `full-text` and `full-text-boosted` alike.
     * - Returning an empty array is normal: a plugin that has nothing to say about this product simply
     *   contributes nothing.
     *
     * This exists because no package can know what a project's own `ProductPageSearch` map-expander
     * plugins put into the index. Rather than requiring projects to subclass the resolver to extend a
     * hardcoded constant, they register a plugin here.
     *
     * @api
     *
     * @param array<string, mixed> $productAbstractStorageData
     * @param array<int, array<string, mixed>> $productConcreteStorageData
     * @param string $localeName
     *
     * @return array<string, array<int, string>>
     */
    public function getLabelsByValue(
        array $productAbstractStorageData,
        array $productConcreteStorageData,
        string $localeName,
    ): array;
}
