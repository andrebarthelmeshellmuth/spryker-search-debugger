<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class LoginPage
{
    /**
     * @var string
     */
    public const URL = '/en/login';

    /**
     * @var string
     */
    public const FORM_FIELD_EMAIL = 'loginForm[email]';

    /**
     * @var string
     */
    public const FORM_FIELD_PASSWORD = 'loginForm[password]';
}
