<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class TokenSourcePage
{
    /**
     * @var string
     */
    public const SELECTOR_CONTAINER = '.token-source-page';

    /**
     * @var string
     */
    public const SELECTOR_TIER_HEADING = '.token-source-tier__heading';

    /**
     * @var string
     */
    public const SELECTOR_FIELD_LABEL = '.token-source-field__label';

    /**
     * @var string
     */
    public const SELECTOR_UNATTRIBUTED_HINT = '.token-source-field__hint';

    /**
     * @var string
     */
    public const SELECTOR_ANALYSIS_LINK = '.token-source-mark__analysis-link';
}
