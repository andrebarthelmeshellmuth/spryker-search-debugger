<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Schema;

use Generated\Shared\Transfer\SearchAnalyzerTransfer;
use Generated\Shared\Transfer\SearchIndexFieldTransfer;
use Generated\Shared\Transfer\SearchIndexSchemaTransfer;

class IndexSchemaMapper implements IndexSchemaMapperInterface
{
    /**
     * Elasticsearch's implicit analyzer for text fields that configure none — the terminal fallback of
     * the resolution rules this mapper applies.
     *
     * @var string
     */
    public const DEFAULT_ANALYZER_NAME = 'standard';

    /**
     * @var string
     */
    protected const FIELD_TYPE_TEXT = 'text';

    /**
     * @param string $indexName
     * @param array<string, mixed> $mapping
     * @param array<string, mixed> $analysisSettings
     *
     * @return \Generated\Shared\Transfer\SearchIndexSchemaTransfer
     */
    public function mapToSearchIndexSchemaTransfer(string $indexName, array $mapping, array $analysisSettings): SearchIndexSchemaTransfer
    {
        $searchIndexSchemaTransfer = (new SearchIndexSchemaTransfer())->setIndexName($indexName);

        foreach ($mapping['properties'] ?? [] as $fieldName => $fieldDefinition) {
            $searchIndexSchemaTransfer->addField(
                $this->mapToSearchIndexFieldTransfer((string)$fieldName, (array)$fieldDefinition),
            );
        }

        foreach ($analysisSettings['analyzer'] ?? [] as $analyzerName => $analyzerDefinition) {
            $searchIndexSchemaTransfer->addAnalyzer(
                $this->mapToSearchAnalyzerTransfer((string)$analyzerName, (array)$analyzerDefinition),
            );
        }

        return $searchIndexSchemaTransfer;
    }

    /**
     * Applies Elasticsearch's own analyzer resolution rules at hydration time: a text field's index
     * analyzer is its `analyzer` or "standard"; its query-time analyzer is its `search_analyzer`, else
     * the resolved index analyzer. Non-text fields carry no analyzer names — they are not analyzed.
     *
     * @param string $fieldName
     * @param array<string, mixed> $fieldDefinition
     *
     * @return \Generated\Shared\Transfer\SearchIndexFieldTransfer
     */
    protected function mapToSearchIndexFieldTransfer(string $fieldName, array $fieldDefinition): SearchIndexFieldTransfer
    {
        $fieldType = (string)($fieldDefinition['type'] ?? '');

        $searchIndexFieldTransfer = (new SearchIndexFieldTransfer())
            ->setName($fieldName)
            ->setType($fieldType);

        if ($fieldType !== static::FIELD_TYPE_TEXT) {
            return $searchIndexFieldTransfer;
        }

        $analyzerName = (string)($fieldDefinition['analyzer'] ?? static::DEFAULT_ANALYZER_NAME);

        return $searchIndexFieldTransfer
            ->setAnalyzerName($analyzerName)
            ->setSearchAnalyzerName((string)($fieldDefinition['search_analyzer'] ?? $analyzerName));
    }

    /**
     * @param string $analyzerName
     * @param array<string, mixed> $analyzerDefinition
     *
     * @return \Generated\Shared\Transfer\SearchAnalyzerTransfer
     */
    protected function mapToSearchAnalyzerTransfer(string $analyzerName, array $analyzerDefinition): SearchAnalyzerTransfer
    {
        return (new SearchAnalyzerTransfer())
            ->setName($analyzerName)
            ->setTokenizerName((string)($analyzerDefinition['tokenizer'] ?? ''))
            ->setCharFilterNames(array_map('strval', (array)($analyzerDefinition['char_filter'] ?? [])))
            ->setFilterNames(array_map('strval', (array)($analyzerDefinition['filter'] ?? [])));
    }
}
