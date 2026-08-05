<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class CheckInstallationPage
{
    /**
     * Only registered when SearchDebugConstants::IS_CHECK_INSTALLATION_PAGE_ENABLED is on — this
     * demoshop enables it via SPRYKER_DEBUG_ENABLED, see config/Shared/config_default-docker.dev.php.
     *
     * @var string
     */
    public const URL = '/en/search-debug/check-installation';

    /**
     * @var string
     */
    public const SELECTOR_CONTAINER = '.check-installation-page';

    /**
     * @var string
     */
    public const SELECTOR_CHECK_PASSED = '.check-installation-check--passed';

    /**
     * @var string
     */
    public const SELECTOR_CHECK_FAILED = '.check-installation-check--failed';

    /**
     * The wording rendered by permission-denied.twig for a logged-out/unpermitted visitor — confirmed
     * live against this exact demoshop.
     *
     * @var string
     */
    public const PERMISSION_DENIED_HEADING = 'Search Debug: permission required';
}
