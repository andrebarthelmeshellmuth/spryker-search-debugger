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
     * Key of the real field=>boost pairs the query's `multi_match` clause actually used (e.g.
     * `['full-text' => 1, 'full-text-boosted' => 5]`), within the search debug result data — captured live
     * off the query by `QueryFieldBoostReader`, never hardcoded or assumed.
     *
     * @var string
     */
    public const KEY_FIELD_BOOSTS = 'fieldBoosts';

    /**
     * Key of the additional score display sections within one product's debug data — contributed by
     * {@see \SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface}
     * plugins (e.g. a ranking package's business-signal breakdown) and rendered generically by the SRP
     * overlay. See the plugin interface for the section shape.
     *
     * @var string
     */
    public const KEY_SCORE_SECTIONS = 'scoreSections';

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

    /**
     * Number of decimal places every score-related number in the SRP overlay is rounded and
     * displayed to: `_score`, matched-token weights, other contributions, and any section a
     * {@see \SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface}
     * plugin contributes (e.g. spryker-community/search-ranking's business-signal breakdown, which
     * reads this same constant for its own pre-built calculation/formula strings — one shared
     * precision for the whole overlay, not two constants that could drift apart).
     *
     * Rounding happens ONLY here, at display time. No business-logic class in this package, or in a
     * contributing plugin, rounds a value before this point — the full-precision float is always
     * what gets passed around and computed with; this constant only controls how many digits
     * `number_format()` (or an equivalent `sprintf('%.<n>f', ...)`) shows.
     *
     * @api
     *
     * @var int
     */
    public const SCORE_DECIMAL_PLACES = 3;
}
