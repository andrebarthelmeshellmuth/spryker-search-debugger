<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidget\Resolver;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\CategoryNodeStorageTransfer;
use SprykerCommunity\Yves\SearchDebugWidget\Resolver\CategoryAncestorNameCollector;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Yves
 * @group SearchDebugWidget
 * @group Resolver
 * @group CategoryAncestorNameCollectorTest
 * Add your own group annotations below this line
 *
 * @property \SprykerCommunityTest\Yves\SearchDebugWidget\SearchDebugWidgetTester $tester
 */
class CategoryAncestorNameCollectorTest extends Unit
{
    /**
     * A real 3-level chain (node -> parent -> grandparent), root's `getParents()` correctly empty.
     *
     * @return void
     */
    public function testCollectWalksTheFullAncestorChain(): void
    {
        // Arrange
        $grandparent = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('All Products');
        $parent = (new CategoryNodeStorageTransfer())->setNodeId(2)->setName('Electrical')->addParents($grandparent);
        $directNode = (new CategoryNodeStorageTransfer())->setNodeId(3)->setName('Cables')->addParents($parent);

        // Act
        $names = (new CategoryAncestorNameCollector())->collect([$directNode]);

        // Assert
        $this->assertSame(['Electrical', 'All Products'], $names);
    }

    /**
     * @return void
     */
    public function testCollectReturnsEmptyArrayForNoDirectNodes(): void
    {
        // Act
        $names = (new CategoryAncestorNameCollector())->collect([]);

        // Assert
        $this->assertSame([], $names);
    }

    /**
     * Two direct nodes sharing a common ancestor must not list that ancestor's name twice.
     *
     * @return void
     */
    public function testCollectDeduplicatesASharedAncestorAcrossMultipleDirectNodes(): void
    {
        // Arrange
        $sharedRoot = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('All Products');
        $directNodeA = (new CategoryNodeStorageTransfer())->setNodeId(2)->setName('Cables')->addParents($sharedRoot);
        $directNodeB = (new CategoryNodeStorageTransfer())->setNodeId(3)->setName('Adapters')->addParents($sharedRoot);

        // Act
        $names = (new CategoryAncestorNameCollector())->collect([$directNodeA, $directNodeB]);

        // Assert
        $this->assertSame(['All Products'], $names);
    }

    /**
     * A cycle (nothing enforces a category tree can't have one at the storage layer) must not infinite
     * loop — the visited-set stops recursion the second time an already-seen node id is reached.
     *
     * @return void
     */
    public function testCollectStopsAtACycleInsteadOfLoopingForever(): void
    {
        // Arrange — node 1's parent is node 2, whose own parent circles back to node 1.
        $nodeOne = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('Node One');
        $nodeTwo = (new CategoryNodeStorageTransfer())->setNodeId(2)->setName('Node Two');
        $nodeOne->addParents($nodeTwo);
        $nodeTwo->addParents($nodeOne);

        $directNode = (new CategoryNodeStorageTransfer())->setNodeId(3)->setName('Direct')->addParents($nodeOne);

        // Act
        $names = (new CategoryAncestorNameCollector())->collect([$directNode]);

        // Assert — both names collected exactly once each, despite the cycle.
        $this->assertSame(['Node One', 'Node Two'], $names);
    }

    /**
     * The hard depth cap terminates a hypothetical chain of nodes WITHOUT ids, which the visited-set
     * cannot register — built here as a long chain of real, uniquely-id'd nodes (so the cycle guard never
     * fires) purely to prove the depth cap is the thing that stops it, at a real, finite depth.
     *
     * @return void
     */
    public function testCollectStopsAtTheMaxDepthEvenWithoutACycle(): void
    {
        // Arrange — a chain of 30 uniquely-id'd ancestors, deeper than MAX_DEPTH (20).
        $chainLength = 30;
        $node = (new CategoryNodeStorageTransfer())->setNodeId(1)->setName('Ancestor 1');

        for ($nodeId = 2; $nodeId <= $chainLength; $nodeId++) {
            $ancestor = (new CategoryNodeStorageTransfer())->setNodeId($nodeId)->setName(sprintf('Ancestor %d', $nodeId));
            $ancestor->addParents($node);
            $node = $ancestor;
        }

        $directNode = (new CategoryNodeStorageTransfer())->setNodeId($chainLength + 1)->setName('Direct')->addParents($node);

        // Act
        $names = (new CategoryAncestorNameCollector())->collect([$directNode]);

        // Assert — collection stopped well short of the full 30-node chain.
        $this->assertLessThan($chainLength, count($names));
    }
}
