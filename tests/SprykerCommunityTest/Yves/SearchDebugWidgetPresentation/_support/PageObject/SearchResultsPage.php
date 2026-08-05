<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class SearchResultsPage
{
    /**
     * ~56 hits in this demoshop's catalog — the reliable baseline query for the score overlay.
     *
     * @var string
     */
    public const URL_CHAIR = '/en/search?q=chair&searchDebugInfo=1';

    /**
     * 0 hits — confirms the overlay/page doesn't break on an empty result set.
     *
     * @var string
     */
    public const URL_JACKET = '/en/search?q=jacket&searchDebugInfo=1';

    /**
     * Matches EUROKRAFT products named "trolley" via the index-time `trolley, handcart` synonym pair.
     *
     * @var string
     */
    public const URL_HANDCART = '/en/search?q=handcart&searchDebugInfo=1';

    /**
     * "&" triggers the unit_symbol_normalizer char filter (& -> and) before tokenizing.
     *
     * @var string
     */
    public const URL_AMPERSAND_QUERY = '/en/search?q=M%26M+chair&searchDebugInfo=1';

    /**
     * Two distinct query tokens, for the per-token color-consistency check.
     *
     * @var string
     */
    public const URL_STEEL_CHAIR = '/en/search?q=steel+chair&searchDebugInfo=1';

    /**
     * @var string
     */
    public const SELECTOR_PRODUCT_WRAPPER = '.search-debug-product-wrapper';

    /**
     * @var string
     */
    public const SELECTOR_SCORE_TRIGGER = '.search-debug-trigger';

    /**
     * @var string
     */
    public const SELECTOR_OVERLAY = '.search-debug-overlay';

    /**
     * @var string
     */
    public const SELECTOR_PIN_TOGGLE = '.search-debug-pin-toggle';

    /**
     * @var string
     */
    public const SELECTOR_MATCHED_TOKEN = '.search-debug-token';

    /**
     * @var string
     */
    public const SELECTOR_TOKEN_SOURCE_LINK = '.search-debug-token-source-link';

    /**
     * @var string
     */
    public const SELECTOR_FINAL_SCORE = '.search-debug-overlay__final-score';

    /**
     * @var string
     */
    public const SELECTOR_QUERY_TOKEN_ROW = '.search-debug-token-row';

    /**
     * @var string
     */
    public const SELECTOR_QUERY_TOKEN_ANALYSIS_LINK = '.search-debug-token__analysis-link';

    /**
     * @var string
     */
    public const SELECTOR_COLLAPSIBLE_SECTION = '.search-debug-overlay__section .search-debug-collapsible';
}
