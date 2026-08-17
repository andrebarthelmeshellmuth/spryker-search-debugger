<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Yves\SearchDebugWidgetPresentation\PageObject;

class AnalyzePage
{
    /**
     * @var string
     */
    public const SELECTOR_CONTAINER = '.search-debug-analyze-page';

    /**
     * @var string
     */
    public const SELECTOR_BACK_LINK = '.search-debug-analyze-page__back';

    /**
     * Scoped to the query-token row specifically — the bare `.search-debug-token` class is also used
     * by the always-visible SRP headline, same reasoning `SearchResultsPage::SELECTOR_MATCHED_TOKEN`
     * already documents.
     *
     * @var string
     */
    public const SELECTOR_QUERY_TOKEN_BADGE = '.search-debug-analyze-row .search-debug-token';

    /**
     * @var string
     */
    public const SELECTOR_WORD_BADGE = '.search-debug-word-badge';

    /**
     * @var string
     */
    public const SELECTOR_WORD_BADGE_MATCH = '.search-debug-word-badge--match';

    /**
     * @var string
     */
    public const SELECTOR_TREE_PANEL = '.search-debug-analyze-trees__panel';

    /**
     * @var string
     */
    public const SELECTOR_TREE_EMPTY = '.search-debug-analyze-trees__panel-empty';

    /**
     * @var string
     */
    public const SELECTOR_HINT = '.search-debug-analyze-page__hint';

    /**
     * @var string
     */
    public const SELECTOR_TREE_ROW = '.search-debug-tree-diagram__row';

    /**
     * @var string
     */
    public const SELECTOR_TREE_NODE = '.search-debug-tree-diagram__node';

    /**
     * @var string
     */
    public const SELECTOR_TREE_NODE_REMOVED = '.search-debug-tree-diagram__node--removed';
}
