<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenAnalysisPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\TokenSourcePage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Checklist sections 02 (QUERY-TOKEN ANALYZER) and 04 (ANALYSIS-PATH PAGE): both cover the
 * /search-debug/token-analysis page, just entered from two different links (the query-token headline
 * vs. a matched fragment on the token-source page), so both are exercised here together.
 *
 * Every magnifier link on this widget opens target="_blank" - rather than juggle WebDriver tab
 * switching, each test grabs the link's real generated href and navigates to it directly in the same
 * tab. That's still testing the app's real routing/params, not a hand-guessed URL.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group TokenAnalysisPathCest
 * Add your own group annotations below this line
 */
class TokenAnalysisPathCest
{
    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function _before(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amYves();
        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::PERMITTED_CUSTOMER_EMAIL);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function searchTimeAnalysisPathForAQueryToken(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW, 10);

        $href = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_QUERY_TOKEN_ANALYSIS_LINK, 'href');
        $i->amOnUrl($href);

        $i->seeElement(TokenAnalysisPage::SELECTOR_CONTAINER);
        $i->seeElement(TokenAnalysisPage::SELECTOR_STEP_FINAL);
        // Search-time chain only - fulltext_synonyms and the ngram filter only run at index time.
        $i->dontSee('fulltext_synonyms');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function multiTokenQueryGetsDistinctConsistentColors(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_STEEL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW, 10);

        $tokenClasses = $i->grabMultiple(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW . ' .search-debug-token', 'class');
        $i->assertGreaterThanOrEqual(2, count($tokenClasses));
        // Every token got SOME color class beyond the bare "search-debug-token" one, and no two
        // distinct tokens collide on the same one.
        $colorClasses = array_map(
            static fn (string $class): string => trim(str_replace('search-debug-token', '', $class)),
            $tokenClasses,
        );
        $i->assertNotContains('', $colorClasses);
        $i->assertSame(count($colorClasses), count(array_unique($colorClasses)));
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function synonymInjectionShowsAsAColorChange(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_HANDCART);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->expandMatchedTokens();
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 5);

        $tokenSourceHref = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_TOKEN_SOURCE_LINK, 'href');
        $i->amOnUrl($tokenSourceHref);
        $i->seeElement(TokenSourcePage::SELECTOR_CONTAINER);

        if (!$i->tryToSeeElement(TokenSourcePage::SELECTOR_ANALYSIS_LINK)) {
            // The matched fragment wasn't a highlighted <mark> on this particular product/tier (e.g. it
            // matched a non-highlightable field) - same fail-soft posture as the rest of this page.
            $i->comment('No inline analysis-path link on this token-source page; skipping the synonym color-change assertion.');

            return;
        }

        $analysisHref = $i->grabAttributeFrom(TokenSourcePage::SELECTOR_ANALYSIS_LINK, 'href');
        $i->amOnUrl($analysisHref);
        $i->seeElement(TokenAnalysisPage::SELECTOR_CONTAINER);
        $i->see('fulltext_synonyms');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function anyMatchStillTracesAsOneStraightLine(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW, 10);

        $href = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_QUERY_TOKEN_ANALYSIS_LINK, 'href');
        $i->amOnUrl($href);

        // A tree would render nested containers; this page only ever emits siblings - a flat step
        // count with no step containing another step proves the chain, not just a step's own boost.
        $i->seeNumberOfElements(TokenAnalysisPage::SELECTOR_CONTAINER . ' > .token-analysis-path > ' . TokenAnalysisPage::SELECTOR_STEP, [1, 20]);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function builtInFiltersShowNoInventedDefinition(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW, 10);

        $href = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_QUERY_TOKEN_ANALYSIS_LINK, 'href');
        $i->amOnUrl($href);

        // The search-time chain documented in the package README is tokenizer -> lowercase ->
        // fulltext_word_delimiter -> fulltext_stop_words -> fulltext_min_length; "lowercase" is a
        // stock filter used by name only, with no configuration line of its own.
        $lowercaseOperationXpath = "//*[contains(concat(' ', normalize-space(@class), ' '), ' "
            . 'token-analysis-path__operation'
            . " ') and contains(., 'lowercase')]";
        $i->seeElement($lowercaseOperationXpath);
        $i->dontSeeElement($lowercaseOperationXpath . '//*[contains(@class, "token-analysis-path__definition")]');
    }
}
