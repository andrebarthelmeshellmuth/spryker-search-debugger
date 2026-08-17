<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Controller;

use Spryker\Yves\Kernel\Controller\AbstractController;
use Spryker\Yves\Kernel\PermissionAwareTrait;
use Spryker\Yves\Kernel\View\View;
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;
use SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router\SearchDebugWidgetRouteProviderPlugin;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\SkuLookupResult;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Entry point of the "you miss a SKU? figure out why it's not here" widget — the SKU-lookup form on the
 * SRP posts (GET, nothing is mutated) here. Resolves the three outcomes {@see SkuLookupResult} documents;
 * only the FOUND-and-currently-ranked-low outcome renders a real page here (a confirmation step, since
 * the analysis tree is equally useful for "why is this ranked so low" and the admin may just want the
 * position, not the full tree) — the other two redirect back to the SRP with a flash message, and the
 * FOUND-but-absent-from-the-result-set outcome redirects straight on to the analyze page.
 *
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory getFactory()
 */
class SkuLookupController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * @var string
     */
    protected const PARAM_SKU = 'sku';

    /**
     * The full current SRP query string (search term + every active facet/sort/page param), captured via
     * a single hidden field the same way `SubmitTicketController::PARAM_QUERY_STRING` does — reconstructs
     * both the "close, go back to the SRP" redirect and the exact filtered result set the rank check has
     * to search within.
     *
     * @var string
     */
    protected const PARAM_QUERY_STRING = 'queryString';

    /**
     * Matches `SprykerShop\Yves\CatalogPage\Plugin\Router\CatalogPageRouteProviderPlugin::ROUTE_NAME_SEARCH`
     * and `SprykerCommunity\Shared\SearchDebug\SearchDebugConfig::REQUEST_PARAM_SEARCH_STRING` — kept as a
     * local string rather than a hard dependency, same standalone posture `SubmitTicketController` in the
     * sibling search-feedback package already keeps toward the shop it's installed into.
     *
     * @var string
     */
    protected const ROUTE_NAME_SEARCH_FALLBACK = 'search';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function indexAction(Request $request): View|RedirectResponse
    {
        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            throw new AccessDeniedHttpException();
        }

        $sku = trim((string)$request->query->get(static::PARAM_SKU, ''));
        $queryString = (string)$request->query->get(static::PARAM_QUERY_STRING, '');
        $originalParameters = $this->parseQueryString($queryString);
        $rawSearchString = $originalParameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_STRING] ?? '';
        $searchString = is_string($rawSearchString) ? $rawSearchString : '';

        if ($sku === '') {
            $this->addErrorMessage('search_debug.sku_lookup.error.empty');

            return $this->redirectResponseInternal(static::ROUTE_NAME_SEARCH_FALLBACK, $originalParameters);
        }

        $result = $this->getFactory()
            ->createSkuLookupResolver()
            ->resolve($sku, $searchString, $originalParameters, $this->getLocale());

        if ($result->status === SkuLookupResult::STATUS_NOT_FOUND) {
            // Flash messages are opaque translation keys with no parameter support in this Spryker
            // version (see `Spryker\Yves\Messenger\FlashMessenger\FlashMessenger::addErrorMessage()`) —
            // the SKU itself is left out rather than string-concatenated into the key; it's already
            // visible in the form the admin just typed it into.
            $this->addErrorMessage('search_debug.sku_lookup.error.not_found');

            return $this->redirectResponseInternal(static::ROUTE_NAME_SEARCH_FALLBACK, $originalParameters);
        }

        if ($result->status === SkuLookupResult::STATUS_NOT_INDEXED) {
            $this->addErrorMessage('search_debug.sku_lookup.error.not_indexed');

            return $this->redirectResponseInternal(static::ROUTE_NAME_SEARCH_FALLBACK, $originalParameters);
        }

        if ($result->rankPosition === null) {
            // Not in the current result set at all — nothing to confirm, straight to the analysis tree.
            return $this->redirectResponseInternal(SearchDebugWidgetRouteProviderPlugin::ROUTE_NAME_ANALYZE, [
                static::PARAM_SKU => $result->productSku,
                static::PARAM_QUERY_STRING => $queryString,
            ]);
        }

        return $this->view(
            [
                'productSku' => $result->productSku,
                'productTitle' => $result->productTitle,
                'rankPosition' => $result->rankPosition,
                'sku' => $sku,
                'queryString' => $queryString,
                'searchFallbackParameters' => $originalParameters,
            ],
            [],
            '@SearchDebugWidget/views/sku-lookup-found/sku-lookup-found.twig',
        );
    }

    /**
     * @param string $queryString
     *
     * @return array<int|string, mixed>
     */
    protected function parseQueryString(string $queryString): array
    {
        parse_str($queryString, $parameters);

        return $parameters;
    }
}
