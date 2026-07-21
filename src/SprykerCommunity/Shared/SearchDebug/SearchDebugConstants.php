<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchDebug;

/**
 * Declares global environment configuration keys. Do not use it for other class constants.
 */
interface SearchDebugConstants
{
    /**
     * Specification:
     * - Toggles whether the `search-debug:check-installation` Yves diagnostic page's route registers at all.
     * - Defaults to enabled so the page helps diagnose a fresh install out of the box.
     * - Set to `false` in a project's production config (e.g. `config_default-production.php`) so the
     *   route never registers there: the URL then 404s exactly like any nonexistent path, rather than
     *   existing-but-denied. A permission check alone would still leak "this route exists and is gated"
     *   to an unauthenticated prober; not registering the route at all removes that signal entirely.
     * - The permission check in {@see \SprykerCommunity\Yves\SearchDebugWidget\Controller\CheckInstallationController}
     *   still applies wherever this flag leaves the route enabled (e.g. staging), so enabling it outside
     *   production does not by itself expose the page to anyone but a permitted customer.
     *
     * @api
     *
     * @var string
     */
    public const IS_CHECK_INSTALLATION_PAGE_ENABLED = 'SEARCH_DEBUG:IS_CHECK_INSTALLATION_PAGE_ENABLED';
}
