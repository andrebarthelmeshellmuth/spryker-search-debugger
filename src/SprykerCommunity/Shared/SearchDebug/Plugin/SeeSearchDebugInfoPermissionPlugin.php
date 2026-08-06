<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchDebug\Plugin;

use Spryker\Shared\PermissionExtension\Dependency\Plugin\PermissionPluginInterface;

/**
 * For Zed & Client PermissionDependencyProvider::getPermissionPlugins() registration
 */
class SeeSearchDebugInfoPermissionPlugin implements PermissionPluginInterface
{
    /**
     * @var string
     */
    public const KEY = 'SeeSearchDebugInfoPermissionPlugin';

    public function getKey(): string
    {
        return static::KEY;
    }
}
