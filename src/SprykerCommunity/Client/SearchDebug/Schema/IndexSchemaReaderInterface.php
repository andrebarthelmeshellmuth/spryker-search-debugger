<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Schema;

use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;
use Generated\Shared\Transfer\SearchIndexSchemaTransfer;

interface IndexSchemaReaderInterface
{
    /**
     * @return \Generated\Shared\Transfer\SearchIndexSchemaTransfer
     */
    public function getPageIndexSchema(): SearchIndexSchemaTransfer;

    /**
     * Looks up one named tokenizer/filter/char_filter definition from the page index's live schema.
     *
     * @param string $componentKind One of the `IndexSchemaMapper::COMPONENT_KIND_*` constants.
     * @param string $componentName
     *
     * @return \Generated\Shared\Transfer\SearchAnalysisComponentTransfer|null Null when $componentKind
     *   is not one of the known constants, or no component of that kind has that name (e.g. it's a
     *   built-in Elasticsearch component used by name only, never customized).
     */
    public function findComponent(string $componentKind, string $componentName): ?SearchAnalysisComponentTransfer;
}
