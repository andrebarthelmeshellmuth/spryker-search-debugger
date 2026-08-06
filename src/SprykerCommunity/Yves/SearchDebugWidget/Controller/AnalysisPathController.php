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
     * Together with {@see PARAM_END_OFFSET}, identifies the ONE SPECIFIC occurrence the magnifying-glass
     * link was built for (see TokenSourceController's per-match links) — see
     * {@see resolveExplicitOffset()} for why this matters.
     *
     * @var string
     */
    protected const PARAM_START_OFFSET = 'startOffset';

    /**
     * @var string
     */
    protected const PARAM_END_OFFSET = 'endOffset';

    /**
     * Selects which analyzer the path is traced through — absent or any other value means the index-time
     * analyzer (tracing a piece of indexed product text, reached from the token-source page); the one
     * recognized non-default value is {@see ANALYZER_SEARCH} (tracing a QUERY string's own tokenization,
     * reached from the SRP overlay's own matched query tokens — that text was never indexed, only
     * searched).
     *
     * @var string
     */
    protected const PARAM_ANALYZER = 'analyzer';

    /**
     * @var string
     */
    protected const ANALYZER_SEARCH = 'search';

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

        $text = (string)$request->query->get(static::PARAM_TEXT, '');

        // Lowercased defensively, same reasoning as TokenSourceController: index-analyzer tokens are
        // always lowercase, so a hand-edited URL's differently-cased token would otherwise report "not
        // found" even for text that genuinely contains it.
        $token = mb_strtolower((string)$request->query->get(static::PARAM_TOKEN, ''));

        if ($text === '' || $token === '') {
            throw new BadRequestHttpException('Both `text` and `token` query parameters are required.');
        }

        $useSearchAnalyzer = $this->resolveUseSearchAnalyzer($request);

        $offset = $this->resolveExplicitOffset($request) ?? $this->findFirstMatchOffset(
            $this->getFactory()->getSearchDebugClient()->getTextTokenOffsets($text, $useSearchAnalyzer),
            $token,
        );

        if ($offset === null) {
            throw new NotFoundHttpException(sprintf('Token "%s" not found in the given text.', $token));
        }

        $path = $this->getFactory()
            ->createAnalysisPathResolver()
            ->resolve($text, $token, $offset['startOffset'], $offset['endOffset'], $useSearchAnalyzer);

        if ($path === null) {
            throw new NotFoundHttpException('Could not reconstruct an analysis path for this token.');
        }

        return $this->view(
            [
                'text' => $text,
                'token' => $token,
                'path' => $this->assignStepColors($path),
            ],
            [],
            '@SearchDebugWidget/views/token-analysis/token-analysis.twig',
        );
    }

    /**
     * Colors each step purely by EXACT TEXT MATCH: the first step takes the first palette color, and every
     * later step whose `text` is byte-identical to one already seen reuses that SAME color rather than
     * advancing — a step whose text has never appeared before takes the next unused color, wrapping past
     * {@see SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT}. This turns the path into a visual diff: a color
     * change between two neighboring boxes means "this operation actually changed the string", same color
     * means "passed through unchanged" (or, later on, "back to a string an earlier step already had").
     *
     * @param array<int, array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, highlightedHtml: string|null}> $path
     *
     * @return array<int, array{text: string, operation: string|null, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, highlightedHtml: string|null, colorClass: string}>
     */
    protected function assignStepColors(array $path): array
    {
        $colorClassByText = [];
        $nextColorIndex = 0;

        return array_map(function (array $step) use (&$colorClassByText, &$nextColorIndex): array {
            if (!isset($colorClassByText[$step['text']])) {
                $colorClassByText[$step['text']] = sprintf(
                    SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN,
                    ($nextColorIndex % SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT) + 1,
                );
                $nextColorIndex++;
            }

            return $step + ['colorClass' => $colorClassByText[$step['text']]];
        }, $path);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    protected function resolveUseSearchAnalyzer(Request $request): bool
    {
        return $request->query->get(static::PARAM_ANALYZER) === static::ANALYZER_SEARCH;
    }

    /**
     * When the magnifying-glass link was built for one SPECIFIC highlighted occurrence (see
     * TokenSourceController's per-match analysis links, which carry {@see PARAM_START_OFFSET}/
     * {@see PARAM_END_OFFSET}), that occurrence's own offsets are used directly instead of
     * {@see findFirstMatchOffset()}'s "first occurrence anywhere in the text" fallback — two occurrences
     * of the "same" matched token TEXT can trace back to genuinely DIFFERENT origin words (e.g. the
     * literal word "switch" vs. an edge-ngram PREFIX match carved out of "switching" elsewhere in the
     * same text), so picking an arbitrary occurrence by text alone can silently show the analysis path
     * for a different word than the one actually clicked. `resolve()` itself still validates that a real
     * token entry exists at whatever offset is finally used (see `AnalysisPathResolver::findToken()`), so
     * a tampered or stale value can only ever produce a 404, never a wrong-but-plausible result.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array{startOffset: int, endOffset: int}|null
     */
    protected function resolveExplicitOffset(Request $request): ?array
    {
        if (!$request->query->has(static::PARAM_START_OFFSET) || !$request->query->has(static::PARAM_END_OFFSET)) {
            return null;
        }

        $startOffset = (int)$request->query->get(static::PARAM_START_OFFSET);
        $endOffset = (int)$request->query->get(static::PARAM_END_OFFSET);

        if ($startOffset < 0 || $endOffset <= $startOffset) {
            return null;
        }

        return ['startOffset' => $startOffset, 'endOffset' => $endOffset];
    }

    /**
     * Fallback for when no specific occurrence is known (a hand-typed URL, or a link generated before
     * this controller understood per-occurrence offsets): picks the first occurrence of $token in the
     * text. Correct for the common case where the matched token IS the origin word verbatim (every
     * occurrence of a literally-repeated word produces the identical analysis path, since the index-time
     * analyzer is deterministic) — but see {@see resolveExplicitOffset()} for why this is only a
     * fallback, not the primary path.
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
