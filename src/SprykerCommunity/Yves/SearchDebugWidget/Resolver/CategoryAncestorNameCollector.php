<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use Generated\Shared\Transfer\CategoryNodeStorageTransfer;

/**
 * Walks the ancestor chain of one or more category tree nodes and collects every ancestor's name, without
 * duplicates — a generic tree-walk concern with nothing specific to token-source resolution; it lives
 * here only because {@see TokenSourceResolver} needs it to label a document element's indirect categories.
 *
 * `CategoryNodeStorageTransfer::getParents()` returns the IMMEDIATE parent only, each one itself carrying
 * its own further-nested `getParents()`, terminating at the root category with an empty list — confirmed
 * live against real category storage data (a 4-level chain, e.g. node -> parent -> grandparent -> root,
 * each level's `node_id` correctly populated). The recursion below is therefore genuinely necessary, not a
 * defensive no-op for an already-flattened list.
 *
 * Two independent stop conditions protect it: a visited-set for cycles (a category tree should never have
 * one, but nothing enforces that at the storage layer), and a hard depth cap for anything the visited-set
 * can't catch — its real job is terminating a hypothetical chain of nodes without ids, which the
 * visited-set cannot register, not an expected real-world tree depth.
 */
class CategoryAncestorNameCollector
{
    /**
     * @var int
     */
    protected const MAX_DEPTH = 20;

    /**
     * @param array<int, \Generated\Shared\Transfer\CategoryNodeStorageTransfer> $categoryNodeStorageTransfers
     *
     * @return array<int, string>
     */
    public function collect(array $categoryNodeStorageTransfers): array
    {
        $namesByNodeId = [];

        foreach ($categoryNodeStorageTransfers as $categoryNodeStorageTransfer) {
            foreach ($categoryNodeStorageTransfer->getParents() as $parentCategoryNodeStorageTransfer) {
                $this->collectAncestorNames($parentCategoryNodeStorageTransfer, $namesByNodeId, 0);
            }
        }

        return array_values($namesByNodeId);
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeStorageTransfer $categoryNodeStorageTransfer
     * @param array<int, string> $namesByNodeId
     * @param int $depth
     *
     * @return void
     */
    protected function collectAncestorNames(
        CategoryNodeStorageTransfer $categoryNodeStorageTransfer,
        array &$namesByNodeId,
        int $depth,
    ): void {
        if ($depth > static::MAX_DEPTH) {
            return;
        }

        $nodeId = $categoryNodeStorageTransfer->getNodeId();

        if ($nodeId !== null && isset($namesByNodeId[$nodeId])) {
            // Already visited (cycle guard) — the name is already collected, nothing more to do.
            return;
        }

        if ($nodeId !== null) {
            $namesByNodeId[$nodeId] = (string)$categoryNodeStorageTransfer->getName();
        }

        foreach ($categoryNodeStorageTransfer->getParents() as $parentCategoryNodeStorageTransfer) {
            $this->collectAncestorNames($parentCategoryNodeStorageTransfer, $namesByNodeId, $depth + 1);
        }
    }
}
