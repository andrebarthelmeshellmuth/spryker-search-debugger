<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Dependency\Plugin;

/**
 * Extension point for other packages to enrich the per-product debug data shown in the SRP overlay —
 * e.g. a ranking package explaining how business signals contributed to the final score.
 */
interface ProductDebugDataExpanderPluginInterface
{
    /**
     * Specification:
     * - Called once per search hit, AFTER the explain tree was parsed, with the debug data collected so
     *   far and the hit's raw Elasticsearch `_source`.
     * - `$productDebugData` contains at least `score` (final `_score`, float|null); when an explanation
     *   was present also `matchedTokens`, `otherContributions`, `scoreFunctions` and `queryScore` (the
     *   wrapped query's own relevance score under a function_score, null otherwise).
     * - To add display sections to the overlay, append to `$productDebugData['scoreSections']`
     *   ({@see \SprykerCommunity\Shared\SearchDebug\SearchDebugConfig::KEY_SCORE_SECTIONS}); each section
     *   is rendered generically: `title` (string), `lines` (list of `label`, optional `calculation`,
     *   `value` (float)), optional `summaryLabel`/`summaryValue` and an optional free-text `formula` line.
     * - Returns the (expanded) debug data.
     *
     * @api
     *
     * @param array<string, mixed> $productDebugData
     * @param array<string, mixed> $documentSource
     *
     * @return array<string, mixed>
     */
    public function expandProductDebugData(array $productDebugData, array $documentSource): array;
}
