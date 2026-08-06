<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\AccessChecker;

use Spryker\Client\Permission\PermissionClientInterface;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

/**
 * The single gate for search debug output, checked by both the query expander (which enables the
 * Elasticsearch `explain` — real extra cost on the search cluster) and the result formatter.
 *
 * The permission is re-checked HERE, in the Client layer, rather than trusted from the request
 * parameter alone: the parameter is only sanitized by the Yves catalog controller, while other entry
 * points into the same Catalog client plugin stack (notably the Glue storefront `catalog-search`
 * resource) pass raw HTTP query parameters straight through. Without this check, an anonymous API
 * caller could switch `explain` on for every hit of every search request.
 */
class SearchDebugAccessChecker implements SearchDebugAccessCheckerInterface
{
    /**
     * @param \Spryker\Client\Permission\PermissionClientInterface $permissionClient
     */
    public function __construct(protected PermissionClientInterface $permissionClient)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $requestParameters
     */
    public function isSearchDebugEnabled(array $requestParameters): bool
    {
        if (empty($requestParameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG])) {
            return false;
        }

        return $this->permissionClient->can(SeeSearchDebugInfoPermissionPlugin::KEY);
    }
}
