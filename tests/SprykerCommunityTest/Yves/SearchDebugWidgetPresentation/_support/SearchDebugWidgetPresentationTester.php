<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation;

use Codeception\Actor;
use Exception;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\LoginPage;
use SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject\SearchResultsPage;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(\SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PHPMD)
 */
class SearchDebugWidgetPresentationTester extends Actor
{
    use _generated\SearchDebugWidgetPresentationTesterActions;

    /**
     * The one customer this demoshop's fixtures grant SeeSearchDebugInfoPermissionPlugin to — see
     * this package's README for why these tests depend on this exact demoshop's seeded data.
     *
     * @var string
     */
    public const PERMITTED_CUSTOMER_EMAIL = 'search-admin@test-company.example';

    /**
     * Same company (test-company) as the permitted customer above, but with no company role
     * assignment at all — the real negative-test control, confirmed against
     * data/import/common/common/company_user_role.csv, which grants the search-admin role to
     * customer_reference DE--35 (search-admin@) only, not DE--1 (this one).
     *
     * @var string
     */
    public const UNPERMITTED_CUSTOMER_EMAIL = 'spencor.hopkin@acme.com';

    /**
     * @var string
     */
    public const CUSTOMER_PASSWORD = 'change123';

    /**
     * @param string $email
     *
     * @return void
     */
    public function loginAsCustomer(string $email): void
    {
        $this->amOnPage(LoginPage::URL);
        $this->submitForm(['name' => 'loginForm'], [
            LoginPage::FORM_FIELD_EMAIL => $email,
            LoginPage::FORM_FIELD_PASSWORD => static::CUSTOMER_PASSWORD,
        ]);
    }

    /**
     * @param string $selector
     *
     * @return bool
     */
    public function tryToSeeElement(string $selector): bool
    {
        try {
            $this->seeElement($selector);

            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * The matched-token breakdown, its BM25 detail, and the token-source link all live inside two
     * nested, collapsed-by-default <details> ("Text signals" > "Matched tokens") - both have to be
     * clicked open before any of that content is visible/interactable. Call this right after opening
     * the score popup (clicking .search-debug-trigger).
     *
     * @return void
     */
    public function expandMatchedTokens(): void
    {
        $this->waitForElementVisible(SearchResultsPage::SELECTOR_TEXT_SIGNALS_SUMMARY, 5);
        $this->click(SearchResultsPage::SELECTOR_TEXT_SIGNALS_SUMMARY);
        $this->waitForElementVisible(SearchResultsPage::SELECTOR_MATCHED_TOKENS_SUMMARY, 5);
        $this->click(SearchResultsPage::SELECTOR_MATCHED_TOKENS_SUMMARY);
    }
}
