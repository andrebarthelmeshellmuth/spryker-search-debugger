<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\AccessChecker;

use Codeception\Test\Unit;
use SprykerCommunity\Client\SearchDebug\AccessChecker\SearchDebugAccessChecker;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use Spryker\Client\Permission\PermissionClientInterface;

/**
 * This is the single gate for search debug output — every entry point (the Yves catalog controller AND the
 * Glue storefront catalog-search resource, which passes raw HTTP query parameters into the same Catalog
 * client plugin stack) goes through it. Proving the invariant here proves it for all of them, which is why
 * these cheap unit tests replace an expensive per-entry-point acceptance test.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group Catalog
 * @group SearchDebug
 * @group SearchDebugAccessCheckerTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Client\SearchDebug\SearchDebugClientTester $tester
 */
class SearchDebugAccessCheckerTest extends Unit
{
    /**
     * @return void
     */
    public function testIsSearchDebugEnabledReturnsTrueWhenTheParameterIsSetAndThePermissionIsGranted(): void
    {
        // Arrange
        $searchDebugAccessChecker = new SearchDebugAccessChecker($this->createPermissionClientMock(true));

        // Act
        $isEnabled = $searchDebugAccessChecker->isSearchDebugEnabled([
            SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG => true,
        ]);

        // Assert
        $this->assertTrue($isEnabled);
    }

    /**
     * The parameter is not access control — it only marks which request wants debug output. A caller that
     * supplies it without holding the permission (e.g. an anonymous Glue API request adding
     * `?searchDebugInfo=1`) must not switch on Elasticsearch `explain`.
     *
     * @dataProvider spoofedParameterValueDataProvider
     *
     * @param mixed $parameterValue
     *
     * @return void
     */
    public function testIsSearchDebugEnabledReturnsFalseWhenThePermissionIsMissing($parameterValue): void
    {
        // Arrange
        $searchDebugAccessChecker = new SearchDebugAccessChecker($this->createPermissionClientMock(false));

        // Act
        $isEnabled = $searchDebugAccessChecker->isSearchDebugEnabled([
            SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG => $parameterValue,
        ]);

        // Assert
        $this->assertFalse($isEnabled);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function spoofedParameterValueDataProvider(): array
    {
        return [
            'boolean true' => [true],
            'string "1" as it arrives from a URL' => ['1'],
            'arbitrary truthy string' => ['yes'],
        ];
    }

    /**
     * The permission must not even be looked up when nothing asked for debug output — a permitted customer
     * browsing normally should not pay for it.
     *
     * @return void
     */
    public function testIsSearchDebugEnabledReturnsFalseAndSkipsThePermissionCheckWhenTheParameterIsAbsent(): void
    {
        // Arrange
        $permissionClientMock = $this->createMock(PermissionClientInterface::class);
        $permissionClientMock->expects($this->never())->method('can');
        $searchDebugAccessChecker = new SearchDebugAccessChecker($permissionClientMock);

        // Act
        $isEnabled = $searchDebugAccessChecker->isSearchDebugEnabled([]);

        // Assert
        $this->assertFalse($isEnabled);
    }

    /**
     * @param bool $can
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|\Spryker\Client\Permission\PermissionClientInterface
     */
    protected function createPermissionClientMock(bool $can): PermissionClientInterface
    {
        $permissionClientMock = $this->createMock(PermissionClientInterface::class);
        $permissionClientMock
            ->method('can')
            ->with(SeeSearchDebugInfoPermissionPlugin::KEY)
            ->willReturn($can);

        return $permissionClientMock;
    }
}
