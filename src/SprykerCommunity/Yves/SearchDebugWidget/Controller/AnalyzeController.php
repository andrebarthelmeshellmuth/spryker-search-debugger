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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The word-level analysis page: the current query's own tokens, one horizontally-scrollable row of
 * clickable word badges per document field, and ONE pinned analysis tree at the bottom for whichever
 * badge was clicked last — see this package's own planning notes for the full design. Reached either
 * directly from `SkuLookupController` (a SKU that isn't in the current result set at all) or via the
 * "Analyze anyway" link on the found-but-ranked-low confirmation page.
 *
 * Deliberately just one pin, not several: an earlier version supported two side by side (comparing a
 * query token's tree against a doc word's tree), with its own close button per panel — dropped in favor
 * of "clicking any badge always shows exactly this one tree", simpler to reason about and to build on.
 *
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory getFactory()
 */
class AnalyzeController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * @var string
     */
    protected const PARAM_SKU = 'sku';

    /**
     * @var string
     */
    protected const PARAM_QUERY_STRING = 'queryString';

    /**
     * @var string
     */
    protected const PARAM_PIN = 'pin';

    /**
     * @var string
     */
    protected const PARAM_PIN_TEXT = 'text';

    /**
     * @var string
     */
    protected const PARAM_PIN_ANALYZER = 'analyzer';

    /**
     * @var string
     */
    protected const PIN_ANALYZER_SEARCH = 'search';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function indexAction(Request $request): View
    {
        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            throw new AccessDeniedHttpException();
        }

        $sku = trim((string)$request->query->get(static::PARAM_SKU, ''));

        if ($sku === '') {
            throw new BadRequestHttpException('The `sku` query parameter is required.');
        }

        $queryString = (string)$request->query->get(static::PARAM_QUERY_STRING, '');
        parse_str($queryString, $originalParameters);
        $searchString = (string)($originalParameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_STRING] ?? '');

        $data = $this->getFactory()
            ->createAnalyzeResolver()
            ->resolve($sku, $searchString, $this->getLocale());

        if ($data === null) {
            throw new NotFoundHttpException(sprintf('Product abstract with SKU %s not found.', $sku));
        }

        $pin = $this->resolvePin($request);

        return $this->view(
            [
                'sku' => $sku,
                'queryString' => $queryString,
                'searchFallbackParameters' => $originalParameters,
                'productSku' => $data['productSku'],
                'productTitle' => $data['productTitle'],
                'queryTokens' => $data['queryTokens'],
                'queryTokenOrigins' => $data['queryTokenOrigins'],
                'fieldRows' => $data['fieldRows'],
                'pin' => $pin,
                'pinnedTree' => $this->resolvePinnedTree($pin),
            ],
            [],
            '@SearchDebugWidget/views/analyze/analyze.twig',
        );
    }

    /**
     * Reads the `pin[text]`/`pin[analyzer]` query parameters — the analyze page's own badge links build
     * these (see `analyze.twig`), so the page stays a plain server-rendered GET flow with no JavaScript
     * required. Null when absent or empty, meaning nothing is pinned yet (the initial "pin hint" state).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array{text: string, analyzer: string}|null
     */
    protected function resolvePin(Request $request): ?array
    {
        $rawPin = $request->query->all(static::PARAM_PIN);
        $text = trim((string)($rawPin[static::PARAM_PIN_TEXT] ?? ''));

        if ($text === '') {
            return null;
        }

        $analyzer = (string)($rawPin[static::PARAM_PIN_ANALYZER] ?? '') === static::PIN_ANALYZER_SEARCH
            ? static::PIN_ANALYZER_SEARCH
            : 'index';

        return ['text' => $text, 'analyzer' => $analyzer];
    }

    /**
     * @param array{text: string, analyzer: string}|null $pin
     *
     * @return array{text: string, analyzer: string, tree: array}|null
     */
    protected function resolvePinnedTree(?array $pin): ?array
    {
        if ($pin === null) {
            return null;
        }

        return $pin + [
            'tree' => $this->getFactory()->getSearchDebugClient()->getTextAnalysisTree($pin['text'], $pin['analyzer'] === static::PIN_ANALYZER_SEARCH),
        ];
    }
}
