<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class SkuLookupPage
{
    /**
     * @var string
     */
    public const SELECTOR_FORM = '.search-debug-sku-lookup__form';

    /**
     * @var string
     */
    public const FORM_FIELD_SKU = 'sku';

    /**
     * @var string
     */
    public const SELECTOR_FOUND_CONTAINER = '.search-debug-sku-lookup-found-page';

    /**
     * @var string
     */
    public const SELECTOR_FOUND_POSITION = '.search-debug-sku-lookup-found-page__position';

    /**
     * @var string
     */
    public const SELECTOR_FOUND_ANALYZE_LINK = '.search-debug-sku-lookup-found-page__actions a.button--primary';

    /**
     * @var string
     */
    public const SELECTOR_FOUND_CLOSE_LINK = '.search-debug-sku-lookup-found-page__actions a:not(.button--primary)';
}
