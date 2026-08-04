<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Plugin;

use Codeception\Test\Unit;
use Spryker\Shared\PermissionExtension\Dependency\Plugin\PermissionPluginInterface;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Plugin
 * @group SeeSearchDebugInfoPermissionPluginTest
 * Add your own group annotations below this line
 */
class SeeSearchDebugInfoPermissionPluginTest extends Unit
{
    public function testGetKeyReturnsTheClassConstant(): void
    {
        // Arrange
        $plugin = new SeeSearchDebugInfoPermissionPlugin();

        // Act
        $key = $plugin->getKey();

        // Assert
        $this->assertSame(SeeSearchDebugInfoPermissionPlugin::KEY, $key);
    }

    /**
     * The permission key is registered on BOTH the Zed and Client `PermissionDependencyProvider` —
     * whichever registers it, the check that gates access to debug data compares against this exact
     * string, so a rename here silently breaks every already-granted permission in the database.
     */
    public function testGetKeyIsTheStableStringSeeSearchDebugInfoPermissionPlugin(): void
    {
        // Arrange
        $plugin = new SeeSearchDebugInfoPermissionPlugin();

        // Act
        $key = $plugin->getKey();

        // Assert
        $this->assertSame('SeeSearchDebugInfoPermissionPlugin', $key);
    }

    public function testImplementsThePermissionPluginInterface(): void
    {
        // Arrange
        $plugin = new SeeSearchDebugInfoPermissionPlugin();

        // Assert
        $this->assertInstanceOf(PermissionPluginInterface::class, $plugin);
    }
}
