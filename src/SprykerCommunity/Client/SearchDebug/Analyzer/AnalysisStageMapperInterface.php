<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

interface AnalysisStageMapperInterface
{
    /**
     * Turns one raw `_analyze?explain=true` response into {@see SearchStringAnalyzer::getAnalysisStages()}'s
     * own per-stage breakdown — every char filter, the tokenizer, and every token filter, in chain order —
     * see that method's own docblock for the full design rationale (this is the same mapping, just
     * extracted into its own collaborator so the analyzer class itself doesn't also have to carry the
     * stage-mapping logic).
     *
     * @param array<string, mixed> $detail
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}>
     */
    public function mapStages(array $detail): array;
}
