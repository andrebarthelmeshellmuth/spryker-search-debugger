<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\Presentation;

use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\AnalyzePage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SkuLookupPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester;

/**
 * Covers `SkuLookupController`/`SkuLookupResolver` — the "you miss a SKU? figure out why it's not here"
 * widget. Was a known gap (see this package's README Status section) until this Cest.
 *
 * Deliberately does not exercise the STATUS_NOT_INDEXED branch: it would need a fixture SKU that
 * resolves as a real product but has no search document, and no such SKU is documented as stable in
 * this demoshop's own fixture data - same "don't hand-guess a fixture" posture the rest of this suite
 * already keeps (see e.g. `TokenAnalysisPathCest`'s href-grabbing convention).
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidgetPresentation
 * @group Presentation
 * @group SkuLookupCest
 * Add your own group annotations below this line
 */
class SkuLookupCest
{
    /**
     * @var string
     */
    protected const QUERY_STRING_CHAIR = 'q=chair&searchDebugInfo=1';

    /**
     * @var string
     */
    protected const QUERY_STRING_JACKET = 'q=jacket&searchDebugInfo=1';

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
    public function emptySkuRedirectsBackToTheSearchWithAnErrorMessage(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage($this->buildLookupUrl($i, '', static::QUERY_STRING_CHAIR));

        $i->seeInCurrentUrl('/search');
        $i->see('Please enter a SKU.');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function unknownSkuRedirectsBackToTheSearchWithAnErrorMessage(SearchDebugWidgetPresentationTester $i): void
    {
        $i->amOnPage($this->buildLookupUrl($i, 'DOES-NOT-EXIST-99999999', static::QUERY_STRING_CHAIR));

        $i->seeInCurrentUrl('/search');
        $i->see('No product with that SKU exists');
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function submittingTheRealFormFindsAProductInTheCurrentResultSetAndShowsItsRank(SearchDebugWidgetPresentationTester $i): void
    {
        $sku = $this->grabARealSkuFromTheChairResults($i);

        // The one test in this Cest that drives the actual SRP form (the rest navigate straight to the
        // controller URL, same convention `TokenAnalysisPathCest` documents) - proves the form's hidden
        // queryString field and `sku` field really reach `SkuLookupController` as wired in
        // page-layout-catalog.twig, not just that the controller behaves correctly in isolation.
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SkuLookupPage::SELECTOR_FORM, 10);
        $i->submitForm(SkuLookupPage::SELECTOR_FORM, [SkuLookupPage::FORM_FIELD_SKU => $sku]);

        $i->seeElement(SkuLookupPage::SELECTOR_FOUND_CONTAINER);
        $positionText = $i->grabTextFrom(SkuLookupPage::SELECTOR_FOUND_POSITION);
        $i->assertMatchesRegularExpression('/\d+/', $positionText);

        $analyzeHref = $i->grabAttributeFrom(SkuLookupPage::SELECTOR_FOUND_ANALYZE_LINK, 'href');
        $i->amOnUrl($analyzeHref);
        $i->seeElement(AnalyzePage::SELECTOR_CONTAINER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function aProductFoundButAbsentFromTheCurrentResultSetSkipsTheConfirmationStep(SearchDebugWidgetPresentationTester $i): void
    {
        $sku = $this->grabARealSkuFromTheChairResults($i);

        // Same real, indexed SKU as above, but scoped to a query with zero hits (URL_JACKET) - the
        // product exists and is indexed, just not present in THIS result set, so there's nothing to
        // confirm a rank for and the controller redirects straight to the analyze page.
        $i->amOnPage($this->buildLookupUrl($i, $sku, static::QUERY_STRING_JACKET));

        $i->dontSeeElement(SkuLookupPage::SELECTOR_FOUND_CONTAINER);
        $i->seeElement(AnalyzePage::SELECTOR_CONTAINER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    public function unpermittedCustomerCannotReachSkuLookupDirectly(SearchDebugWidgetPresentationTester $i): void
    {
        $sku = $this->grabARealSkuFromTheChairResults($i);
        $url = $this->buildLookupUrl($i, $sku, static::QUERY_STRING_CHAIR);

        $i->loginAsCustomer(SearchDebugWidgetPresentationTester::UNPERMITTED_CUSTOMER_EMAIL);
        $i->amOnPage($url);

        $i->dontSeeElement(SkuLookupPage::SELECTOR_FOUND_CONTAINER);
        $i->dontSeeElement(AnalyzePage::SELECTOR_CONTAINER);
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     */
    protected function grabARealSkuFromTheChairResults(SearchDebugWidgetPresentationTester $i): string
    {
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_SCORE_TRIGGER, 10);
        $i->click(SearchResultsPage::SELECTOR_SCORE_TRIGGER);
        $i->waitForElementVisible(SearchResultsPage::SELECTOR_ANALYZE_LINK, 5);

        $analyzeHref = $i->grabAttributeFrom(SearchResultsPage::SELECTOR_ANALYZE_LINK, 'href');
        parse_str((string)parse_url($analyzeHref, PHP_URL_QUERY), $parameters);

        $sku = $parameters['sku'] ?? null;
        $i->assertTrue(is_string($sku) && $sku !== '', 'Expected a real sku= query parameter on the analyze link.');

        return $sku;
    }

    /**
     * @param \SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\SearchDebugWidgetPresentationTester $i
     * @param string $sku
     * @param string $queryString
     */
    protected function buildLookupUrl(SearchDebugWidgetPresentationTester $i, string $sku, string $queryString): string
    {
        // Grabbing the current (relative) URL first, rather than hardcoding a locale prefix, keeps this
        // working whether the suite runs against /en/ or another configured locale.
        $i->amOnPage(SearchResultsPage::URL_CHAIR);
        $currentPath = (string)parse_url((string)$i->grabFromCurrentUrl(), PHP_URL_PATH);
        $localePrefix = rtrim(dirname($currentPath), '/');

        return $localePrefix . '/search-debug/sku-lookup?sku=' . rawurlencode($sku) . '&queryString=' . rawurlencode($queryString);
    }
}
