<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Document;

interface PageDocumentReaderInterface
{
    /**
     * @param string $resourceName
     * @param string $identifier
     * @param string $localeName
     *
     * @return array<string, mixed>|null
     */
    public function findPageDocumentData(string $resourceName, string $identifier, string $localeName): ?array;
}
