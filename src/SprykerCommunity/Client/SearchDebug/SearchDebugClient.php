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
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{token: string, startOffset: int, endOffset: int}>
     */
    public function getTextTokenOffsets(string $text, bool $useSearchAnalyzer = false): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getTokenOffsets($text, $useSearchAnalyzer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string> $texts
     *
     * @return array<string, array<array{token: string, startOffset: int, endOffset: int}>>
     */
    public function getTextTokenOffsetsForTexts(array $texts): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getTokenOffsetsForTexts($texts);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $text
     * @param bool $useSearchAnalyzer
     *
     * @return array<array{operation: string, definition: string|null, componentKind: string|null, componentName: string|null, definitionTruncated: bool, tokens: array<array{token: string, startOffset: int, endOffset: int}>}>
     */
    public function getTextAnalysisStages(string $text, bool $useSearchAnalyzer = false): array
    {
        return $this->getFactory()
            ->createSearchStringAnalyzer()
            ->getAnalysisStages($text, $useSearchAnalyzer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $componentKind
     * @param string $componentName
     *
     * @return array{name: string, type: string, config: array<string, mixed>}|null
     */
    public function getComponentConfig(string $componentKind, string $componentName): ?array
    {
        $component = $this->getFactory()
            ->createIndexSchemaReader()
            ->findComponent($componentKind, $componentName);

        if ($component === null) {
            return null;
        }

        return [
            'name' => $component->getName(),
            'type' => $component->getType(),
            'config' => $component->getConfig(),
        ];
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
