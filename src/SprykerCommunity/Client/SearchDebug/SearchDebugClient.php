<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug;

use Spryker\Client\Kernel\AbstractClient;

/**
 * @method \SprykerCommunity\Client\SearchDebug\SearchDebugFactory getFactory()
 */
class SearchDebugClient extends AbstractClient implements SearchDebugClientInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $searchString
     *
     * @return array<string>
     */
    public function getSearchStringTokens(string $searchString): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getTokens($searchString);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $text
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTextTokenOffsets(string $text): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getTokenOffsets($text);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $text
     *
     * @return array<array{operation: string, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getTextAnalysisStages(string $text): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getAnalysisStages($text);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $resourceName
     * @param string $identifier
     * @param string $localeName
     *
     * @return array<string, mixed>|null
     */
    public function findPageDocumentData(string $resourceName, string $identifier, string $localeName): ?array
    {
        return $this->getFactory()
            ->createPageDocumentReader()
            ->findPageDocumentData($resourceName, $identifier, $localeName);
    }
}
