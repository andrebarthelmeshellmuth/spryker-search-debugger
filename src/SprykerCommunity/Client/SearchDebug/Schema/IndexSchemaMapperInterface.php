<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Schema;

use Generated\Shared\Transfer\SearchIndexSchemaTransfer;

interface IndexSchemaMapperInterface
{
    /**
     * @param string $indexName
     * @param array<string, mixed> $mapping
     * @param array<string, mixed> $analysisSettings
     *
     * @return \Generated\Shared\Transfer\SearchIndexSchemaTransfer
     */
    public function mapToSearchIndexSchemaTransfer(string $indexName, array $mapping, array $analysisSettings): SearchIndexSchemaTransfer;
}
