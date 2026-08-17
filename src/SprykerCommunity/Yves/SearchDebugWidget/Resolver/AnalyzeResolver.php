<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use Spryker\Client\ProductStorage\ProductStorageClientInterface;
use SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface;
use SprykerCommunity\Shared\SearchDebug\Utf16\Utf16CodeUnitConverter;

class AnalyzeResolver implements AnalyzeResolverInterface
{
    /**
     * @var string
     */
    protected const MAPPING_TYPE_SKU = 'sku';

    /**
     * @param \Spryker\Client\ProductStorage\ProductStorageClientInterface $productStorageClient
     * @param \SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface $searchDebugClient
     * @param \SprykerCommunity\Yves\SearchDebugWidget\Resolver\ProductSourceMapBuilder $productSourceMapBuilder
     */
    public function __construct(
        protected ProductStorageClientInterface $productStorageClient,
        protected SearchDebugClientInterface $searchDebugClient,
        protected ProductSourceMapBuilder $productSourceMapBuilder,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sku
     * @param string $searchString
     * @param string $localeName
     *
     * @return array{
     *     productSku: string,
     *     productTitle: string,
     *     queryTokens: array<string>,
     *     queryTokenOrigins: array<string, string>,
     *     fieldRows: array<int, array{labelKey: string, words: array<int, array{word: string, matchesQuery: bool}>}>,
     * }|null
     */
    public function resolve(string $sku, string $searchString, string $localeName): ?array
    {
        $productData = $this->productStorageClient->findProductAbstractStorageDataByMapping(
            static::MAPPING_TYPE_SKU,
            $sku,
            $localeName,
        );

        if ($productData === null) {
            return null;
        }

        $queryTokens = $searchString !== '' ? $this->searchDebugClient->getSearchStringTokens($searchString) : [];
        $fieldRows = $this->productSourceMapBuilder->buildFieldRows($productData, $localeName);

        return [
            'productSku' => (string)($productData['sku'] ?? $sku),
            'productTitle' => (string)($productData['name'] ?? ''),
            'queryTokens' => $queryTokens,
            'queryTokenOrigins' => $this->resolveQueryTokenOrigins($searchString),
            'fieldRows' => $this->buildWordRows($fieldRows, $queryTokens),
        ];
    }

    /**
     * A query TOKEN badge pins the RAW WORD SPAN of the original search string that produced it (via the
     * search-time analyzer's own token offsets), not the token text itself — so clicking it opens the
     * SAME kind of isolated, single-word tree a field-word badge does, rather than re-running analysis on
     * a string that was never actually typed by anyone. First occurrence wins for a repeated token,
     * mirroring the "first occurrence wins" simplification `SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN`
     * already documents for badge coloring — a repeated query token always traces to the same tree
     * regardless of which occurrence's badge was clicked.
     *
     * @param string $searchString
     *
     * @return array<string, string>
     */
    protected function resolveQueryTokenOrigins(string $searchString): array
    {
        if ($searchString === '') {
            return [];
        }

        $origins = [];
        foreach ($this->searchDebugClient->getTextTokenOffsets($searchString, true) as $tokenOffset) {
            if (isset($origins[$tokenOffset['token']])) {
                continue;
            }

            $origins[$tokenOffset['token']] = $this->sliceCodeUnits(
                $searchString,
                $tokenOffset['startOffset'],
                $tokenOffset['endOffset'],
            );
        }

        return $origins;
    }

    /**
     * Same UTF-16-code-unit slicing {@see \SprykerCommunity\Yves\SearchDebugWidget\Resolver\AnalysisPathResolver}
     * uses for the identical reason (Lucene offsets are Java string indices, not Unicode code points).
     *
     * @param string $text
     * @param int $startCodeUnit
     * @param int $endCodeUnit
     */
    protected function sliceCodeUnits(string $text, int $startCodeUnit, int $endCodeUnit): string
    {
        $textUtf16 = Utf16CodeUnitConverter::toUtf16($text);
        $lengthInCodeUnits = Utf16CodeUnitConverter::lengthOf($textUtf16);

        $startCodeUnit = max(0, min($startCodeUnit, $lengthInCodeUnits));
        $endCodeUnit = max($startCodeUnit, min($endCodeUnit, $lengthInCodeUnits));

        return Utf16CodeUnitConverter::slice($textUtf16, $startCodeUnit, $endCodeUnit);
    }

    /**
     * @param array<int, array{sourceKey: string, labelKey: string, elements: array<int, string>}> $fieldRows
     * @param array<string> $queryTokens
     *
     * @return array<int, array{labelKey: string, words: array<int, array{word: string, matchesQuery: bool}>}>
     */
    protected function buildWordRows(array $fieldRows, array $queryTokens): array
    {
        $queryTokenSet = array_fill_keys($queryTokens, true);

        $allElements = [];
        foreach ($fieldRows as $row) {
            array_push($allElements, ...$row['elements']);
        }
        $allElements = array_values(array_unique(array_filter($allElements, static fn (string $element): bool => $element !== '')));

        // ONE batched `_analyze` call for every distinct raw element across every field, reading back its
        // real WORD boundaries — see SearchDebugClientInterface::getTextWordSpansForTexts()'s own
        // docblock for why these come from the real analyzer rather than a hand-rolled split: a guessed
        // boundary can cut a char filter's own input in half (e.g. a decimal-comma normalizer), silently
        // producing wrong results for whatever gets analyzed on either side of that cut.
        $wordSpansByElement = $allElements !== [] ? $this->searchDebugClient->getTextWordSpansForTexts($allElements) : [];

        $wordsByRowIndex = [];
        $allWords = [];

        foreach ($fieldRows as $rowIndex => $row) {
            $words = [];
            foreach ($row['elements'] as $element) {
                foreach ($wordSpansByElement[$element] ?? [] as $span) {
                    $words[] = $this->sliceCodeUnits($element, $span['startOffset'], $span['endOffset']);
                }
            }

            // Deliberately a VALUE-based dedup (array_unique over a plain list), never a dict keyed by the
            // word itself — PHP silently retypes a purely-numeric STRING key (e.g. a product attribute
            // value like "24") to a real `int`, which would then surface as an `int` mixed into an
            // otherwise-`string` list the moment the keys are read back out with `array_keys()`. Same
            // class of bug `TokenSourceResolver::warmTokenOffsetsCache()` already documents guarding
            // against — confirmed live here too: an all-digit word crashed
            // `getTextTokenOffsetsForTexts()`'s strict `string $text` type hint further down the call
            // chain before this fix.
            $words = array_values(array_unique($words));

            $wordsByRowIndex[$rowIndex] = $words;
            array_push($allWords, ...$words);
        }

        $allWords = array_values(array_unique($allWords));

        // A second, separate batched `_analyze` call: the word SPANS above only established where a word
        // starts and ends, not what it produces once fully analyzed (lowercase, stemmer, synonym,
        // decompounder, ...) — see SearchDebugClientInterface::getTextTokenOffsetsForTexts()'s own
        // docblock. Deliberately the INDEX-time analyzer (the default `useSearchAnalyzer = false`): this
        // asks "what token(s) would this raw document WORD produce if indexed", the same
        // asymmetric-analyzer posture `SearchStringAnalyzer::getTokenOffsets()` documents at length —
        // comparable against the query's own SEARCH-time tokens below only because that is genuinely the
        // real-world matching question.
        $tokensByWord = $allWords !== [] ? $this->searchDebugClient->getTextTokenOffsetsForTexts($allWords) : [];

        $fieldWordRows = [];
        foreach ($fieldRows as $rowIndex => $row) {
            $words = [];
            foreach ($wordsByRowIndex[$rowIndex] as $word) {
                $producedTokens = array_column($tokensByWord[$word] ?? [], 'token');
                $matchesQuery = array_intersect_key(array_fill_keys($producedTokens, true), $queryTokenSet) !== [];

                $words[] = ['word' => $word, 'matchesQuery' => $matchesQuery];
            }

            $fieldWordRows[] = ['labelKey' => $row['labelKey'], 'words' => $words];
        }

        return $fieldWordRows;
    }
}
