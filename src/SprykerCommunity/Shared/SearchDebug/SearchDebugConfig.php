<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchDebug;

use Spryker\Shared\Kernel\AbstractSharedConfig;

class SearchDebugConfig extends AbstractSharedConfig
{
    /**
     * Internal request parameter that opts a search request into debug output.
     *
     * Set server-side by the catalog controller (after stripping it from user input) so that debug
     * output is produced for the search results page only, and not for category listings that share the
     * same query stack. It is an opt-in marker, NOT the access control: the permission itself is
     * enforced in the Client layer by `SearchDebugAccessChecker`, which every entry point passes
     * through — see that class for why the parameter alone cannot be trusted.
     *
     * @var string
     */
    public const REQUEST_PARAM_SEARCH_DEBUG = 'searchDebugInfo';

    /**
     * The catalog search string request parameter, as used by the storefront search route and by
     * `SprykerShop\Yves\CatalogPage\Controller\CatalogController`.
     *
     * @var string
     */
    public const REQUEST_PARAM_SEARCH_STRING = 'q';

    /**
     * Key under which the search debug result formatter publishes its data in the search result array.
     *
     * @var string
     */
    public const SEARCH_RESULT_KEY = 'searchDebug';

    /**
     * Key of the analyzer tokens of the search string, within the search debug result data.
     *
     * @var string
     */
    public const KEY_TOKENS = 'tokens';

    /**
     * Key of the per-product debug data (keyed by abstract product id), within the search debug result data.
     *
     * @var string
     */
    public const KEY_PRODUCTS = 'products';

    /**
     * CSS class assigned to a query token's badge, `%d` being the 1-based color index.
     *
     * @see \SprykerCommunity\Yves\SearchDebugWidget\Theme\default\components\molecules\search-debug-tokens\search-debug-tokens.scss
     *
     * @var string
     */
    public const TOKEN_COLOR_CLASS_PATTERN = 'search-debug-token--color-%d';

    /**
     * Number of token colors in the palette. Colors repeat past this many distinct tokens in one query.
     *
     * Must equal the number of `.search-debug-token--color-*` classes the stylesheet defines — the
     * stylesheet generates them from this same count, so change both together.
     *
     * @see \SprykerCommunity\Yves\SearchDebugWidget\Theme\default\components\molecules\search-debug-tokens\search-debug-tokens.scss
     *
     * @var int
     */
    public const TOKEN_COLOR_CLASS_COUNT = 8;
}
