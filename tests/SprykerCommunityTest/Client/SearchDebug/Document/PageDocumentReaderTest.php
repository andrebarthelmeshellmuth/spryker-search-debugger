<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug\Document;

use Codeception\Test\Unit;
use Elastica\Exception\NotFoundException;
use Generated\Shared\Transfer\SearchContextTransfer;
use Generated\Shared\Transfer\SearchDocumentTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Generated\Shared\Transfer\SynchronizationDataTransfer;
use Spryker\Client\SearchElasticsearch\Dependency\Client\SearchElasticsearchToStoreClientInterface;
use Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface;
use Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface;
use Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface;
use Spryker\Service\Synchronization\SynchronizationServiceInterface;
use SprykerCommunity\Client\SearchDebug\Document\PageDocumentReader;
use SprykerCommunity\Client\SearchDebug\SearchDebugConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group Document
 * @group PageDocumentReaderTest
 * Add your own group annotations below this line
 */
class PageDocumentReaderTest extends Unit
{
    /**
     * The document id is built through the SAME synchronization key builder the publish pipeline uses —
     * this locks down that `generateDocumentId()` feeds it a `SynchronizationDataTransfer` assembled from
     * the current store, the given locale, and the given identifier, rather than re-implementing the key
     * format by hand.
     *
     * @return void
     */
    public function testFindPageDocumentDataBuildsTheDocumentIdFromTheCurrentStoreLocaleAndIdentifier(): void
    {
        // Arrange
        $keyGeneratorPluginMock = $this->createMock(SynchronizationKeyGeneratorPluginInterface::class);
        $keyGeneratorPluginMock
            ->expects($this->once())
            ->method('generateKey')
            ->with($this->callback(function (SynchronizationDataTransfer $synchronizationDataTransfer) {
                return $synchronizationDataTransfer->getStore() === 'DE'
                    && $synchronizationDataTransfer->getLocale() === 'de_DE'
                    && $synchronizationDataTransfer->getReference() === '238';
            }))
            ->willReturn('product_abstract:de:de_de:238');

        $documentReaderMock = $this->createMock(DocumentReaderInterface::class);
        $documentReaderMock
            ->expects($this->once())
            ->method('readDocument')
            ->with($this->callback(function (SearchDocumentTransfer $searchDocumentTransfer) {
                return $searchDocumentTransfer->getId() === 'product_abstract:de:de_de:238';
            }))
            ->willReturn((new SearchDocumentTransfer())->setData(['full-text' => ['Cable']]));

        $pageDocumentReader = $this->createPageDocumentReader($documentReaderMock, $keyGeneratorPluginMock);

        // Act
        $data = $pageDocumentReader->findPageDocumentData('product_abstract', '238', 'de_DE');

        // Assert
        $this->assertSame(['full-text' => ['Cable']], $data);
    }

    /**
     * The search context is expanded through `SearchContextExpanderInterface` (which may add e.g. a store
     * or merchant-relation constraint) rather than passed through unexpanded — locking down that the
     * config's page source identifier reaches the expander untouched.
     *
     * @return void
     */
    public function testFindPageDocumentDataExpandsTheSearchContextWithThePageSourceIdentifier(): void
    {
        // Arrange
        $expandedSearchContextTransfer = (new SearchContextTransfer())->setSourceIdentifier('page')->setStoreName('DE');

        $searchContextExpanderMock = $this->createMock(SearchContextExpanderInterface::class);
        $searchContextExpanderMock
            ->expects($this->once())
            ->method('expandSearchContext')
            ->with($this->callback(function (SearchContextTransfer $searchContextTransfer) {
                return $searchContextTransfer->getSourceIdentifier() === 'page';
            }))
            ->willReturn($expandedSearchContextTransfer);

        $documentReaderMock = $this->createMock(DocumentReaderInterface::class);
        $documentReaderMock
            ->expects($this->once())
            ->method('readDocument')
            ->with($this->callback(function (SearchDocumentTransfer $searchDocumentTransfer) use ($expandedSearchContextTransfer) {
                return $searchDocumentTransfer->getSearchContext() === $expandedSearchContextTransfer;
            }))
            ->willReturn((new SearchDocumentTransfer())->setData([]));

        $pageDocumentReader = $this->createPageDocumentReader($documentReaderMock, null, $searchContextExpanderMock);

        // Act
        $pageDocumentReader->findPageDocumentData('product_abstract', '238', 'de_DE');
    }

    /**
     * A missing document (Elastica's `NotFoundException`, which implements `ExceptionInterface`) degrades
     * to "no document data" rather than propagating — the same fail-soft posture as `SearchStringAnalyzer`
     * elsewhere in this package.
     *
     * @return void
     */
    public function testFindPageDocumentDataReturnsNullWhenTheDocumentReaderThrowsAnElasticaException(): void
    {
        // Arrange
        $documentReaderMock = $this->createMock(DocumentReaderInterface::class);
        $documentReaderMock->method('readDocument')->willThrowException(new NotFoundException());

        $pageDocumentReader = $this->createPageDocumentReader($documentReaderMock);

        // Act
        $data = $pageDocumentReader->findPageDocumentData('product_abstract', '238', 'de_DE');

        // Assert
        $this->assertNull($data);
    }

    /**
     * @return void
     */
    public function testFindPageDocumentDataReturnsTheDocumentDataOnSuccess(): void
    {
        // Arrange
        $documentReaderMock = $this->createMock(DocumentReaderInterface::class);
        $documentReaderMock
            ->method('readDocument')
            ->willReturn((new SearchDocumentTransfer())->setData(['full-text' => ['Hammer']]));

        $pageDocumentReader = $this->createPageDocumentReader($documentReaderMock);

        // Act
        $data = $pageDocumentReader->findPageDocumentData('product_abstract', '238', 'de_DE');

        // Assert
        $this->assertSame(['full-text' => ['Hammer']], $data);
    }

    /**
     * @param \Spryker\Client\SearchElasticsearch\Reader\DocumentReaderInterface $documentReaderMock
     * @param (\Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface&\PHPUnit\Framework\MockObject\MockObject)|null $keyGeneratorPluginMock
     * @param (\Spryker\Client\SearchElasticsearch\SearchContextExpander\SearchContextExpanderInterface&\PHPUnit\Framework\MockObject\MockObject)|null $searchContextExpanderMock
     *
     * @return \SprykerCommunity\Client\SearchDebug\Document\PageDocumentReader
     */
    protected function createPageDocumentReader(
        DocumentReaderInterface $documentReaderMock,
        ?object $keyGeneratorPluginMock = null,
        ?object $searchContextExpanderMock = null,
    ): PageDocumentReader {
        $keyGeneratorPluginMock ??= $this->createMock(SynchronizationKeyGeneratorPluginInterface::class);
        $keyGeneratorPluginMock->method('generateKey')->willReturn('product_abstract:de:de_de:238');

        $synchronizationServiceMock = $this->createMock(SynchronizationServiceInterface::class);
        $synchronizationServiceMock->method('getStorageKeyBuilder')->with('product_abstract')->willReturn($keyGeneratorPluginMock);

        $searchContextExpanderMock ??= $this->createMock(SearchContextExpanderInterface::class);
        $searchContextExpanderMock->method('expandSearchContext')->willReturnArgument(0);

        $storeClientMock = $this->createMock(SearchElasticsearchToStoreClientInterface::class);
        $storeClientMock->method('getCurrentStore')->willReturn((new StoreTransfer())->setName('DE'));

        return new PageDocumentReader(
            $documentReaderMock,
            $searchContextExpanderMock,
            $synchronizationServiceMock,
            $storeClientMock,
            new SearchDebugConfig(),
        );
    }
}
