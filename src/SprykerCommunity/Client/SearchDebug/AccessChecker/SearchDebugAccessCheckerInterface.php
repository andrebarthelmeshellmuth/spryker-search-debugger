<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\AccessChecker;

interface SearchDebugAccessCheckerInterface
{
    /**
     * Specification:
     * - Returns `true` only when the search debug request parameter is set AND the current customer holds
     *   the search debug permission.
     * - Returns `false` for any request that merely carries the parameter without the permission.
     *
     * @param array<string, mixed> $requestParameters
     */
    public function isSearchDebugEnabled(array $requestParameters): bool;
}
