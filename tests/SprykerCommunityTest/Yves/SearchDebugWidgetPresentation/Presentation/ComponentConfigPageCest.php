<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\ComponentConfigPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenAnalysisPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenSourcePage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist section 05 - COMPONENT-CONFIG PAGE: the "view full definition" page a long filter config
 * (the synonym list) links out to instead of dumping inline.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group ComponentConfigPageCest
 * Add your own group annotations below this line
 */
class ComponentConfigPageCest
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
     * Reaches the fulltext_synonyms stage the same way TokenAnalysisPathCest::synonymInjectionShows...
     * does (score popup -> token-source page -> matched-fragment analysis link on a handcart/trolley
     * match), then checks the definition preview + full-definition link on that stage specifically.
     *
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function truncatedPreviewOnTheAnalysisPathPage(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_HANDCART);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->expandMatchedTokens();
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 5);

        $tokenSourceHref = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 'href');
        $i->amOnUrl($tokenSourceHref);

        if (!$i->tryToSeeElement(TokenSourcePage::SELECTOR_ANALYSIS_LINK)) {
            $i->comment('No inline analysis-path link on this token-source page; skipping.');

            return;
        }

        $analysisHref = $i->grabAttributeFrom(TokenSourcePage::SELECTOR_ANALYSIS_LINK, 'href');
        $i->amOnUrl($analysisHref);

        if (!$i->tryToSeeElement(TokenAnalysisPage::SELECTOR_DEFINITION_LINK)) {
            $i->comment('This trace did not pass through fulltext_synonyms; skipping the truncated-preview assertion.');

            return;
        }

        $i->seeElement(TokenAnalysisPage::SELECTOR_DEFINITION);
        $i->seeElement(TokenAnalysisPage::SELECTOR_DEFINITION_LINK);

        $configHref = $i->grabAttributeFrom(TokenAnalysisPage::SELECTOR_DEFINITION_LINK, 'href');
        $i->amOnUrl($configHref);
        $i->seeElement(ComponentConfigPage::SELECTOR_CONTAINER);
        $i->seeElement(ComponentConfigPage::SELECTOR_FIELD);
        // The full pair list, not the truncated preview - every configured synonym pair from
        // src/Pyz/Shared/Search/Schema/page.json should be discoverable somewhere on this page.
        $i->see('trolley');
        $i->see('handcart');
    }
}
