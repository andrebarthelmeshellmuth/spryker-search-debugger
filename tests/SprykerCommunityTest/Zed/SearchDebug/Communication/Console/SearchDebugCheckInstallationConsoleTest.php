<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchDebug\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchDebug\Communication\Console\SearchDebugCheckInstallationConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Every check here — core namespace, plugin class existence, search-engine reachability, page-index
 * shape, explain support — deliberately hits this demoshop's OWN real installation rather than a mock:
 * this command exists specifically to diagnose a REAL installation (see its own docblock), and it
 * constructs its own Elastica client directly rather than going through any injectable
 * Facade/Factory/Locator seam, so there is nothing to substitute even if a mock were desirable. This
 * demoshop is expected to be fully wired (core namespace registered, both required classes autoloadable,
 * a real reachable search engine with an exported page index that supports explain) — asserted on
 * accordingly, same portability tradeoff every sibling package's own CheckInstallationConsoleTest already
 * accepts.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchDebug
 * @group Communication
 * @group Console
 * @group SearchDebugCheckInstallationConsoleTest
 * @group NeedsProject
 */
class SearchDebugCheckInstallationConsoleTest extends Unit
{
    public function testSucceedsAndReportsEveryCheckAgainstTheRealInstallation(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchDebugCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('core namespace "SprykerCommunity" is registered', $commandTester->getDisplay());
        $this->assertStringContainsString('permission plugin class is loadable', $commandTester->getDisplay());
        $this->assertStringContainsString('search debug client class is loadable', $commandTester->getDisplay());
        $this->assertStringContainsString('search engine reachable', $commandTester->getDisplay());
        $this->assertStringContainsString('page index found', $commandTester->getDisplay());
        $this->assertStringContainsString('explain output is available and non-empty', $commandTester->getDisplay());
        $this->assertStringContainsString('Everything checkable from the CLI is in place.', $commandTester->getDisplay());
    }

    protected function createCommandTester(): CommandTester
    {
        $console = new SearchDebugCheckInstallationConsole();

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchDebugCheckInstallationConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
