<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenAnalysisPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist section 07 - EDGE CASES: the overlay degrading gracefully instead of erroring.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group EdgeCasesCest
 * Add your own group annotations below this line
 */
class EdgeCasesCest
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
    public function zeroResultQueryDoesNotBreakThePage(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_JACKET);
        // No PHP error page, no broken layout - a normal "no results" render.
        $i->dontSee('Fatal error');
        $i->dontSee('Exception');
        $i->dontSeeElement(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     *
     * @return void
     */
    public function ampersandCharFilterNormalizesBeforeTokenizing(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_AMPERSAND_QUERY);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW, 10);

        // Several tokens come out of "M&M chair" - only one of them descends from the "&". Check every
        // token's analysis path rather than guessing which row it is.
        $hrefs = $i->grabMultiple(SearchResultsPage::SELECTOR_QUERY_TOKEN_ANALYSIS_LINK, 'href');
        $i->assertNotEmpty($hrefs);

        $sawCharFilterMapping = false;
        foreach ($hrefs as $href) {
            $i->amOnUrl($href);
            $i->seeElement(TokenAnalysisPage::SELECTOR_CONTAINER);

            if ($i->tryToSeeElement(TokenAnalysisPage::SELECTOR_CONTAINER) && str_contains($i->grabTextFrom(TokenAnalysisPage::SELECTOR_CONTAINER), 'and')) {
                $sawCharFilterMapping = true;

                break;
            }
        }

        // The very first stage of that one token's chain is the char filter mapping "&" -> "and",
        // before the tokenizer ever runs.
        $i->assertTrue($sawCharFilterMapping, 'Expected at least one query token\'s analysis path to show the "&" -> "and" char-filter mapping.');
    }
}
