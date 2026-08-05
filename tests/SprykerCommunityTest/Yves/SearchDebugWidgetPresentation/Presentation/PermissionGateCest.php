<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist section 06 - PERMISSION GATE (negative tests): every debug surface is gated by the
 * SeeSearchDebugInfoPermissionPlugin permission, re-checked client-side, not trusted from the request.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group PermissionGateCest
 * Add your own group annotations below this line
 */
class PermissionGateCest
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
    public function anonymousShopperSeesNothing(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function loggedInCustomerWithoutTheRoleSeesNothing(SearchDebugWidgetPresentationTester $i): void
    {
        // Same test-company as the permitted customer, but no company role assignment - see the
        // Tester's own UNPERMITTED_CUSTOMER_EMAIL constant for how this was confirmed against the
        // fixture CSVs. Being logged in alone isn't enough; the Company Role must carry the permission.
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::UNPERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->dontSeeElement(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function spoofingTheQueryParamDoesNotHelp(SearchDebugWidgetPresentationTester $i): void
    {
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::UNPERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->seeInCurrentUrl('searchDebugInfo=1');
        // The param survives in the URL, but the overlay still doesn't render - it's re-checked
        // against the permission in the Client layer, which also covers non-Yves entry points.
        $i->dontSeeElement(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function permittedCustomerDoesSeeIt(SearchDebugWidgetPresentationTester $i): void
    {
        // The positive control for the three negative tests above - proves the gate is a real
        // permission check, not simply "the overlay never renders in this environment".
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
    }
}
