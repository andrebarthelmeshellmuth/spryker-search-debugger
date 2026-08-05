<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class TokenAnalysisPage
{
    /**
     * @var string
     */
    public const SELECTOR_CONTAINER = '.token-analysis-page';

    /**
     * @var string
     */
    public const SELECTOR_STEP = '.token-analysis-path__step';

    /**
     * @var string
     */
    public const SELECTOR_STEP_FINAL = '.token-analysis-path__step--final';

    /**
     * @var string
     */
    public const SELECTOR_OPERATION = '.token-analysis-path__operation';

    /**
     * @var string
     */
    public const SELECTOR_DEFINITION = '.token-analysis-path__definition';

    /**
     * @var string
     */
    public const SELECTOR_DEFINITION_LINK = '.token-analysis-path__definition-link';
}
