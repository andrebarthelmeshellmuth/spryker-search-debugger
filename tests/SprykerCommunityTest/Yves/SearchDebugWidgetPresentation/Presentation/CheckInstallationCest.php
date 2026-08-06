<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\CheckInstallationPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist section 00 - PRE-FLIGHT (Yves half only; the CLI `search-debug:check-installation`
 * command is out of scope for a browser suite).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group CheckInstallationCest
 * Add your own group annotations below this line
 */
class CheckInstallationCest
{
    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function _before(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amYves();
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function loggedOutVisitorSeesPermissionDenied(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(CheckInstallationPage::URL);
        $i->see(CheckInstallationPage::PERMISSION_DENIED_HEADING);
        $i->dontSeeElement(CheckInstallationPage::SELECTOR_CONTAINER . ' .check-installation-page__list');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function permittedCustomerSeesTheRealChecklist(SearchDebugWidgetPresentationTester $i): void
    {
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(CheckInstallationPage::URL);
        $i->dontSee(CheckInstallationPage::PERMISSION_DENIED_HEADING);
        $i->seeElement(CheckInstallationPage::SELECTOR_CONTAINER);
        // At least one check row (passed or failed) must render — this is the wiring confirmation
        // itself, not a claim that every check passes (that's what the CLI command judges in detail).
        $i->assertTrue(
            $i->tryToSeeElement(CheckInstallationPage::SELECTOR_CHECK_PASSED)
            || $i->tryToSeeElement(CheckInstallationPage::SELECTOR_CHECK_FAILED),
        );
    }
}
