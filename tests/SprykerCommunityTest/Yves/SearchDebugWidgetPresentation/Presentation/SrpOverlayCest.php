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
 * Checklist section 01 - SRP OVERLAY: the per-product score badge and its popup.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group SrpOverlayCest
 * Add your own group annotations below this line
 */
class SrpOverlayCest
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
     * @return void
     */
    public function scoreBadgeAppearsPerProduct(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->seeElement(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function pinToggleKeepsThePopupOpen(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_PIN_TOGGLE, 5);
        $i->click(SearchResultsPage::SELECTOR_PIN_TOGGLE);
        $i->seeElement(SearchResultsPage::SELECTOR_PIN_TOGGLE . '[aria-pressed="true"]');
        // Move the mouse elsewhere: only a real mouseout can prove the pin actually overrides
        // hover-close, a plain seeElement right after the click cannot.
        $i->moveMouseOver('body', 5, 5);
        $i->wait(1);
        $i->seeElement(SearchResultsPage::SELECTOR_OVERLAY);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function matchedTokenBm25BreakdownExpands(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_MATCHED_TOKEN, 5);
        $i->see('Matched tokens');
        $i->see('Boost');
        $i->see('Inverse document frequency');
        $i->see('Term frequency');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function otherContributionsAndFinalScoreReconcile(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $badgeText = $i->grabTextFrom(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_FINAL_SCORE, 5);
        $finalScoreText = $i->grabTextFrom(SearchResultsPage::SELECTOR_FINAL_SCORE);

        $badgeScore = static::extractScoreNumber($badgeText);
        $finalScore = static::extractScoreNumber($finalScoreText);

        $i->assertNotNull($badgeScore);
        $i->assertSame($badgeScore, $finalScore);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function businessSignalsSectionRendersWhenSearchRankingIsInstalled(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_COLLAPSIBLE_SECTION, 5);
        // Generic per SearchDebugConfig::KEY_SCORE_SECTIONS - present here because this demoshop wires
        // SearchRankingProductDebugDataExpanderPlugin in (see the sibling search-ranking checklist).
        // A shop without search-ranking installed would simply not show this section at all.
        $i->see('Business signals');
    }

    /**
     * @param string $text
     *
     * @return string|null
     */
    protected static function extractScoreNumber(string $text): ?string
    {
        if (preg_match('/(\d+\.\d+)/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
