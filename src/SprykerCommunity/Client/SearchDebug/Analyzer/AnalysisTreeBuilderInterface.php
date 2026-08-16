<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

interface AnalysisTreeBuilderInterface
{
    /**
     * Turns {@see SearchStringAnalyzer::getAnalysisStages()}'s flat per-stage token lists into a
     * branching diagram laid out by STAGE: one row per analyzer stage, every token alive at that stage,
     * and a flat list of parent→child edges connecting a row's tokens to the specific tokens they
     * produced in the next row — see {@see SearchStringAnalyzer::getAnalysisTree()}'s own docblock for
     * the full design rationale (this is the same tree, just extracted into its own collaborator so the
     * analyzer class itself doesn't also have to carry the tree-layout logic).
     *
     * @param array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, tokens: array<array{token: string, startOffset: int, endOffset: int, position: int}>}> $stages
     *
     * @return array{
     *     stages: array<int, array{label: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, isStem: bool, nodes: array<int, array{id: string, token: string, isRemoved: bool}>}>,
     *     edges: array<int, array{from: string, to: string}>,
     * }
     */
    public function build(array $stages): array;
}
