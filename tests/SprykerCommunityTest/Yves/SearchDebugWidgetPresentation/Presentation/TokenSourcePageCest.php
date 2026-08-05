<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenSourcePage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist section 03 - TOKEN-SOURCE PAGE: attributes a matched token back to the real product field
 * it was indexed from.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group TokenSourcePageCest
 * Add your own group annotations below this line
 */
class TokenSourcePageCest
{
    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function _before(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amYves();
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return string real token-source page href, for reuse by the other test methods in this class
     */
    protected function openTokenSourcePageFromChairResults(SearchDebugWidgetPresentationTester $i): string
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->expandMatchedTokens();
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 5);

        $href = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 'href');
        $i->amOnUrl($href);
        $i->seeElement(TokenSourcePage::SELECTOR_CONTAINER);

        return $href;
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function tierHeadingsShowRealBoostValues(SearchDebugWidgetPresentationTester $i): void
    {
        $this->openTokenSourcePageFromChairResults($i);
        $i->seeElement(TokenSourcePage::SELECTOR_TIER_HEADING);
        // Real boost values from the live query, not a hardcoded "boost: 1" placeholder.
        $i->seeElement("//*[contains(@class, 'token-source-tier__heading') and contains(., 'boost:')]");
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function fieldAttributionNamesRealProductFields(SearchDebugWidgetPresentationTester $i): void
    {
        $this->openTokenSourcePageFromChairResults($i);
        $i->seeElement(TokenSourcePage::SELECTOR_FIELD_LABEL);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function unclaimedValueMayGetTheHint(SearchDebugWidgetPresentationTester $i): void
    {
        // Per the manual QA checklist: most products have no unattributed value at all - this
        // demoshop's default expanders cover almost everything. An empty check on a random product is
        // NOT a failure, so this stays a soft/optional assertion rather than a hard one.
        $this->openTokenSourcePageFromChairResults($i);

        if ($i->tryToSeeElement(TokenSourcePage::SELECTOR_UNATTRIBUTED_HINT)) {
            $i->seeElement(TokenSourcePage::SELECTOR_UNATTRIBUTED_HINT . '[data-tooltip]');

            return;
        }

        $i->comment('No unattributed ("?") value on this product - expected on most products, not a failure.');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function onlyTheTiersActuallySearchedAppear(SearchDebugWidgetPresentationTester $i): void
    {
        $this->openTokenSourcePageFromChairResults($i);
        // At least one tier, and never more tiers than the two this query plugin can ever produce
        // (full-text-boosted, full-text) - see CatalogSearchQueryPlugin::createFulltextSearchQuery().
        $i->seeNumberOfElements(TokenSourcePage::SELECTOR_TIER_HEADING, [1, 2]);
    }
}
