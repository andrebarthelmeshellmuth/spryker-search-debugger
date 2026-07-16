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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method \SprykerCommunity\Yves\SearchDebugWidget\SearchDebugWidgetFactory getFactory()
 */
class AnalysisPathController extends AbstractController
{
    use PermissionAwareTrait;

    /**
     * The exact document element text the analysis path is reconstructed for — re-analyzed server-side
     * (not looked up from storage again), so this controller works for ANY matched element regardless of
     * which product/source it came from, mirroring `TokenSourceController`'s own re-derive-nothing-through-
     * the-URL posture but without needing a SKU round trip at all here.
     *
     * @var string
     */
    protected const PARAM_TEXT = 'text';

    /**
     * @var string
     */
    protected const PARAM_TOKEN = 'token';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return \Spryker\Yves\Kernel\View\View
     */
    public function indexAction(Request $request): View
    {
        if (!$this->can(SeeSearchDebugInfoPermissionPlugin::KEY)) {
            throw new AccessDeniedHttpException();
        }

        $text = (string)$request->query->get(static::PARAM_TEXT, '');

        // Lowercased defensively, same reasoning as TokenSourceController: index-analyzer tokens are
        // always lowercase, so a hand-edited URL's differently-cased token would otherwise report "not
        // found" even for text that genuinely contains it.
        $token = mb_strtolower((string)$request->query->get(static::PARAM_TOKEN, ''));

        if ($text === '' || $token === '') {
            throw new BadRequestHttpException('Both `text` and `token` query parameters are required.');
        }

        $offset = $this->findFirstMatchOffset(
            $this->getFactory()->getSearchDebugClient()->getTextTokenOffsets($text),
            $token,
        );

        if ($offset === null) {
            throw new NotFoundHttpException(sprintf('Token "%s" not found in the given text.', $token));
        }

        $path = $this->getFactory()
            ->createAnalysisPathResolver()
            ->resolve($text, $token, $offset['startOffset'], $offset['endOffset']);

        if ($path === null) {
            throw new NotFoundHttpException('Could not reconstruct an analysis path for this token.');
        }

        return $this->view(
            [
                'text' => $text,
                'token' => $token,
                'path' => $path,
            ],
            [],
            '@SearchDebugWidget/views/token-analysis/token-analysis.twig',
        );
    }

    /**
     * The FIRST occurrence is enough: the magnifying-glass link that reaches this controller was built
     * from an already-computed match on this exact text, and every occurrence of the same token in the
     * same text produces the identical analysis path (the index-time analyzer is deterministic), so
     * there is nothing to disambiguate by picking a specific occurrence.
     *
     * @param array<array{token: string, startOffset: int, endOffset: int}> $tokenOffsets
     * @param string $token
     *
     * @return array{startOffset: int, endOffset: int}|null
     */
    protected function findFirstMatchOffset(array $tokenOffsets, string $token): ?array
    {
        foreach ($tokenOffsets as $tokenOffset) {
            if ($tokenOffset['token'] === $token) {
                return ['startOffset' => $tokenOffset['startOffset'], 'endOffset' => $tokenOffset['endOffset']];
            }
        }

        return null;
    }
}
