<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\AnalyzePage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Covers `AnalyzeController`/`AnalyzeResolver` — the /search-debug/analyze word-level analysis page:
 * query-token and per-field word badges, single-pin tree rendering, and the removed-token marker. Was a
 * known gap (see this package's README Status section) until this Cest; the underlying tree-building
 * algorithm itself was already unit-tested via `AnalysisTreeBuilderTest`.
 *
 * Same target="_blank"-avoidance convention `TokenAnalysisPathCest` documents: every badge/link here is
 * grabbed by its real generated href and navigated to directly, rather than clicked (which would open a
 * new tab WebDriver isn't attached to).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group WordLevelAnalysisCest
 * Add your own group annotations below this line
 */
class WordLevelAnalysisCest
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
    public function analyzeLinkFromTheOverlayOpensTheWordLevelAnalysisPage(SearchDebugWidgetPresentationTester $i): void
    {
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);

        $i->seeElement(AnalyzePage::SELECTOR_CONTAINER);
        // Nothing pinned yet - the initial state is the hint, not a tree.
        $i->seeElement(AnalyzePage::SELECTOR_HINT);
        $i->dontSeeElement(AnalyzePage::SELECTOR_TREE_PANEL);
        $i->seeElement(AnalyzePage::SELECTOR_QUERY_TOKEN_BADGE);
        $i->seeElement(AnalyzePage::SELECTOR_WORD_BADGE);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function nearMissWordsAreFlaggedBeforeAnythingIsClicked(SearchDebugWidgetPresentationTester $i): void
    {
        // "chair" is itself one of the words on a chair product's own title/description, so at least
        // one field word badge must come back flagged as sharing a produced token with the query -
        // this is the "highlight near-misses before anything is even clicked" behavior
        // AnalyzeResolverInterface's own docblock describes.
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);

        $i->seeElement(AnalyzePage::SELECTOR_WORD_BADGE_MATCH);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function clickingAQueryTokenBadgePinsItsAnalysisTree(SearchDebugWidgetPresentationTester $i): void
    {
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);
        $i->waitForElementVisible(AnalyzePage::SELECTOR_QUERY_TOKEN_BADGE, 10);

        $badgeHref = $i->grabAttributeFrom(AnalyzePage::SELECTOR_QUERY_TOKEN_BADGE, 'href');
        $i->amOnUrl($badgeHref);

        $i->dontSeeElement(AnalyzePage::SELECTOR_HINT);
        $i->seeElement(AnalyzePage::SELECTOR_TREE_PANEL);
        $i->seeElement(AnalyzePage::SELECTOR_TREE_ROW);
        // Deliberately exactly one panel - clicking any badge always shows exactly one tree.
        $i->seeNumberOfElements(AnalyzePage::SELECTOR_TREE_PANEL, 1);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function clickingAFieldWordBadgeReplacesThePreviouslyPinnedTree(SearchDebugWidgetPresentationTester $i): void
    {
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);
        $i->waitForElementVisible(AnalyzePage::SELECTOR_QUERY_TOKEN_BADGE, 10);

        $queryBadgeHref = $i->grabAttributeFrom(AnalyzePage::SELECTOR_QUERY_TOKEN_BADGE, 'href');
        $i->amOnUrl($queryBadgeHref);
        $i->seeElement(AnalyzePage::SELECTOR_TREE_PANEL);
        $firstPanelText = $i->grabTextFrom(AnalyzePage::SELECTOR_TREE_PANEL);

        $i->waitForElementVisible(AnalyzePage::SELECTOR_WORD_BADGE, 10);
        $wordBadgeHref = $i->grabAttributeFrom(AnalyzePage::SELECTOR_WORD_BADGE, 'href');
        $i->amOnUrl($wordBadgeHref);

        $i->seeElement(AnalyzePage::SELECTOR_TREE_PANEL);
        $i->seeNumberOfElements(AnalyzePage::SELECTOR_TREE_PANEL, 1);
        $secondPanelText = $i->grabTextFrom(AnalyzePage::SELECTOR_TREE_PANEL);
        $i->assertNotSame($firstPanelText, $secondPanelText, 'Expected the pinned panel to switch to the newly clicked badge, not accumulate.');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function aTokenWithNoSuccessorIsMarkedRemovedSomewhereInTheChain(SearchDebugWidgetPresentationTester $i): void
    {
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);
        $i->waitForElementVisible(AnalyzePage::SELECTOR_WORD_BADGE, 10);

        $badgeHrefs = $i->grabMultiple(AnalyzePage::SELECTOR_WORD_BADGE, 'href');
        $i->assertNotEmpty($badgeHrefs);

        $sawRemovedNode = false;
        // Which particular word hits a removed-token stage (stop-word/min-length filters) depends on
        // this environment's own catalog content, not on this test - scan a bounded number of badges
        // rather than guessing one, same fail-soft posture EdgeCasesCest's char-filter test already uses.
        foreach (array_slice($badgeHrefs, 0, 15) as $badgeHref) {
            $i->amOnUrl($badgeHref);

            if ($i->tryToSeeElement(AnalyzePage::SELECTOR_TREE_NODE_REMOVED)) {
                $sawRemovedNode = true;

                break;
            }
        }

        $i->assertTrue($sawRemovedNode, 'Expected at least one of the first 15 field words to trace a removed token in its tree.');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function backToResultsReturnsToTheOriginalSearch(SearchDebugWidgetPresentationTester $i): void
    {
        $href = $this->grabAnalyzeLinkHref($i);
        $i->amOnUrl($href);

        $backHref = $i->grabAttributeFrom(AnalyzePage::SELECTOR_BACK_LINK, 'href');
        $i->amOnUrl($backHref);

        $i->seeElement(SearchResultsPage::SELECTOR_QUERY_TOKEN_ROW);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function unpermittedCustomerCannotReachTheAnalyzePageDirectly(SearchDebugWidgetPresentationTester $i): void
    {
        // The link itself is only ever rendered for a permitted customer, so a real analyze URL has to
        // be captured while still permitted, then replayed after switching to the unpermitted customer -
        // same "spoofing doesn't help" posture PermissionGateCest already covers for the overlay.
        $href = $this->grabAnalyzeLinkHref($i);

        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::UNPERMITTED_CUSTOMER_EMAIL);
        $i->amOnUrl($href);

        $i->dontSeeElement(AnalyzePage::SELECTOR_CONTAINER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    protected function grabAnalyzeLinkHref(SearchDebugWidgetPresentationTester $i): string
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ANALYZE_LINK, 5);

        return $i->grabAttributeFrom(SearchResultsPage::SELECTOR_ANALYZE_LINK, 'href');
    }
}
