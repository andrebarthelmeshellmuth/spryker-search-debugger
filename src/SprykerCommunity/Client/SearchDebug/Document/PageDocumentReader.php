<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Document;

use Elastica\Exception\ExceptionInterface;
use Generated\Shared\Transfer\SearchContextTransfer;
use Generated\Shared\Transfer\SearchDocumentTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface;
use Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface;
use Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface;
use Spryker\Service\Synchronization\SynchronizationServiceInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;

class PageDocumentReader implements PageDocumentReaderInterface
{
    /**
     * @param \Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface $documentReader
     * @param \Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface $searchContextExpander
     * @param \Spryker\Service\Synchronization\SynchronizationServiceInterface $synchronizationService
     * @param \Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface $storeClient
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugConfig $config
     */
    public function __construct(
        protected DocumentReaderInterface $documentReader,
        protected SearchContextExpanderInterface $searchContextExpander,
        protected SynchronizationServiceInterface $synchronizationService,
        protected SearchElasticsearchToStoreClientInterface $storeClient,
        protected SearchDebugConfig $config,
    ) {
    }

    /**
     * @param string $resourceName
     * @param string $identifier
     * @param string $localeName
     *
     * @return array<string, mixed>|null
     */
    public function findPageDocumentData(string $resourceName, string $identifier, string $localeName): ?array
    {
        $searchDocumentTransfer = (new SearchDocumentTransfer())
            ->setId($this->generateDocumentId($resourceName, $identifier, $localeName))
            ->setSearchContext($this->createSearchContext());

        try {
            return $this->documentReader->readDocument($searchDocumentTransfer)->getData();
        } catch (ExceptionInterface) {
            // Missing document (Elastica NotFoundException) and an unreachable Elasticsearch both
            // degrade to "no document data" — same fail-soft posture as SearchStringAnalyzer.
            return null;
        }
    }

    /**
     * Search document ids follow the synchronization key format (`resource:store:locale:reference`,
     * e.g. `product_abstract:de:de_de:238`) — generated here through the same synchronization service
     * key builder the publish pipeline uses, rather than re-implementing the format by hand.
     *
     * @param string $resourceName
     * @param string $identifier
     * @param string $localeName
     */
    protected function generateDocumentId(string $resourceName, string $identifier, string $localeName): string
    {
        $synchronizationDataTransfer = (new SynchronizationDataTransfer())
            ->setStore($this->storeClient->getCurrentStore()->getName())
            ->setLocale($localeName)
            ->setReference($identifier);

        return $this->synchronizationService
            ->getStorageKeyBuilder($resourceName)
            ->generateKey($synchronizationDataTransfer);
    }

    protected function createSearchContext(): SearchContextTransfer
    {
        $searchContextTransfer = (new SearchContextTransfer())
            ->setSourceIdentifier($this->config->getPageSourceIdentifier());

        return $this->searchContextExpander->expandSearchContext($searchContextTransfer);
    }
}
