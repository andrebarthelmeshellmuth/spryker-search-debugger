<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Client\SearchDebug;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;
use SprykerCommunity\Client\SearchDebug\Analyzer\SearchStringAnalyzerInterface;
use SprykerCommunity\Client\SearchDebug\Document\PageDocumentReaderInterface;
use SprykerCommunity\Client\SearchDebug\Schema\IndexSchemaReaderInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClient;
use SprykerCommunity\Client\SearchDebug\SearchDebugFactory;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Client
 * @group SearchDebug
 * @group SearchDebugClientTest
 * Add your own group annotations below this line
 */
class SearchDebugClientTest extends Unit
{
    /**
     * @return void
     */
    public function testGetSearchStringTokensDelegatesToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())->method('getTokens')->with('cable')->willReturn(['cable']);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $tokens = $client->getSearchStringTokens('cable');

        // Assert
        $this->assertSame(['cable'], $tokens);
    }

    /**
     * @return void
     */
    public function testGetTextTokenOffsetsDelegatesToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $offsets = [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]];

        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())->method('getTokenOffsets')->with('cable', false)->willReturn($offsets);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $result = $client->getTextTokenOffsets('cable');

        // Assert
        $this->assertSame($offsets, $result);
    }

    /**
     * @return void
     */
    public function testGetTextTokenOffsetsForwardsTheSearchAnalyzerFlagToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $offsets = [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]];

        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())->method('getTokenOffsets')->with('cable', true)->willReturn($offsets);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $result = $client->getTextTokenOffsets('cable', true);

        // Assert
        $this->assertSame($offsets, $result);
    }

    /**
     * @return void
     */
    public function testGetTextTokenOffsetsForTextsDelegatesToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $offsetsByText = ['cable' => [['token' => 'cable', 'startOffset' => 0, 'endOffset' => 5]], 'rope' => []];

        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())
            ->method('getTokenOffsetsForTexts')
            ->with(['cable', 'rope'])
            ->willReturn($offsetsByText);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $result = $client->getTextTokenOffsetsForTexts(['cable', 'rope']);

        // Assert
        $this->assertSame($offsetsByText, $result);
    }

    /**
     * @return void
     */
    public function testGetTextAnalysisStagesDelegatesToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $stages = [
            [
                'operation' => 'tokenizer: standard',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [],
            ],
        ];

        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())->method('getAnalysisStages')->with('cable', false)->willReturn($stages);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $result = $client->getTextAnalysisStages('cable');

        // Assert
        $this->assertSame($stages, $result);
    }

    /**
     * @return void
     */
    public function testGetTextAnalysisStagesForwardsTheSearchAnalyzerFlagToTheSearchStringAnalyzer(): void
    {
        // Arrange
        $stages = [
            [
                'operation' => 'analyzer: fulltext_search_analyzer',
                'definition' => null,
                'componentKind' => null,
                'componentName' => null,
                'definitionTruncated' => false,
                'tokens' => [],
            ],
        ];

        $searchStringAnalyzerMock = $this->createMock(SearchStringAnalyzerInterface::class);
        $searchStringAnalyzerMock->expects($this->once())->method('getAnalysisStages')->with('cable', true)->willReturn($stages);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createSearchStringAnalyzer'])
            ->getMock();
        $factoryMock->method('createSearchStringAnalyzer')->willReturn($searchStringAnalyzerMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $result = $client->getTextAnalysisStages('cable', true);

        // Assert
        $this->assertSame($stages, $result);
    }

    /**
     * @return void
     */
    public function testGetComponentConfigMapsTheComponentTransferIntoAnArray(): void
    {
        // Arrange
        $componentTransfer = (new SearchAnalysisComponentTransfer())
            ->setName('fulltext_index_ngram_filter')
            ->setType('filter')
            ->setConfig(['type' => 'edge_ngram', 'min_gram' => '2', 'max_gram' => '20']);

        $indexSchemaReaderMock = $this->createMock(IndexSchemaReaderInterface::class);
        $indexSchemaReaderMock
            ->expects($this->once())
            ->method('findComponent')
            ->with('filter', 'fulltext_index_ngram_filter')
            ->willReturn($componentTransfer);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createIndexSchemaReader'])
            ->getMock();
        $factoryMock->method('createIndexSchemaReader')->willReturn($indexSchemaReaderMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $component = $client->getComponentConfig('filter', 'fulltext_index_ngram_filter');

        // Assert
        $this->assertSame([
            'name' => 'fulltext_index_ngram_filter',
            'type' => 'filter',
            'config' => ['type' => 'edge_ngram', 'min_gram' => '2', 'max_gram' => '20'],
        ], $component);
    }

    /**
     * Unlike the other delegating methods here, this one has a real branch: it must translate the reader's
     * `null` ("no such component") into a `null` return, not into an array with null values.
     *
     * @return void
     */
    public function testGetComponentConfigReturnsNullWhenNoComponentIsFound(): void
    {
        // Arrange
        $indexSchemaReaderMock = $this->createMock(IndexSchemaReaderInterface::class);
        $indexSchemaReaderMock->method('findComponent')->willReturn(null);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createIndexSchemaReader'])
            ->getMock();
        $factoryMock->method('createIndexSchemaReader')->willReturn($indexSchemaReaderMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $component = $client->getComponentConfig('filter', 'unknown_filter');

        // Assert
        $this->assertNull($component);
    }

    /**
     * @return void
     */
    public function testFindPageDocumentDataDelegatesToThePageDocumentReader(): void
    {
        // Arrange
        $pageDocumentReaderMock = $this->createMock(PageDocumentReaderInterface::class);
        $pageDocumentReaderMock
            ->expects($this->once())
            ->method('findPageDocumentData')
            ->with('product_abstract', '238', 'de_DE')
            ->willReturn(['full-text' => ['Cable']]);

        $factoryMock = $this->getMockBuilder(SearchDebugFactory::class)
            ->onlyMethods(['createPageDocumentReader'])
            ->getMock();
        $factoryMock->method('createPageDocumentReader')->willReturn($pageDocumentReaderMock);

        $client = new SearchDebugClient();
        $client->setFactory($factoryMock);

        // Act
        $data = $client->findPageDocumentData('product_abstract', '238', 'de_DE');

        // Assert
        $this->assertSame(['full-text' => ['Cable']], $data);
    }
}
