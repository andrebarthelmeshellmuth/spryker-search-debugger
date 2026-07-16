<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;

class ComponentDefinitionFormatter implements ComponentDefinitionFormatterInterface
{
    /**
     * @var int
     */
    protected const CONFIG_LIST_PREVIEW_ITEM_LIMIT = 5;

    /**
     * High enough that a realistic 5-item list preview plus its "(N total)" suffix survives intact
     * (confirmed live: a 5-rule synonym preview came out at 189 chars) — this limit exists to catch
     * genuinely pathological single values (a very long regex `pattern`, for instance), not to shave a
     * few characters off an already-reasonable preview. A limit tight enough to cut the "(N total)"
     * marker off mid-preview would be worse than no limit at all: truncated with no indication there was
     * ever more to see.
     *
     * @var int
     */
    protected const DEFINITION_CHARACTER_LIMIT = 220;

    /**
     * `truncated` tells the caller whether ANYTHING was left out of `label` — a long config list capped
     * to a preview, or the whole line capped by the overall character limit — so a UI layer with a place
     * to put a "view full definition" link (re-fetching the untruncated config server-side, keyed by
     * this component's kind + name) knows when showing one is actually worth it.
     *
     * @param \Generated\Shared\Transfer\SearchAnalysisComponentTransfer|null $component
     *
     * @return array{label: string, truncated: bool}|null
     */
    public function format(?SearchAnalysisComponentTransfer $component): ?array
    {
        if ($component === null) {
            return null;
        }

        $parameters = [];
        $truncated = false;

        foreach ($component->getConfig() as $key => $value) {
            $parameters[] = $key . ': ' . $this->formatConfigValue($value);

            if (is_array($value) && count($value) > static::CONFIG_LIST_PREVIEW_ITEM_LIMIT) {
                $truncated = true;
            }
        }

        $label = $parameters === []
            ? $component->getType()
            : sprintf('%s (%s)', $component->getType(), implode(', ', $parameters));

        // Belt-and-suspenders on top of the list preview above: a config value can still be an
        // arbitrarily long single scalar (e.g. a long regex `pattern`) or a nested structure (a
        // `multiplexer`/`condition` filter's own sub-filter chain) that the per-value formatting above
        // doesn't specifically cap — so the whole line gets one final hard limit too, never a silent wall
        // of text on the debug page.
        if (mb_strlen($label) > static::DEFINITION_CHARACTER_LIMIT) {
            $label = mb_substr($label, 0, static::DEFINITION_CHARACTER_LIMIT - 1) . '…';
            $truncated = true;
        }

        return ['label' => $label, 'truncated' => $truncated];
    }

    /**
     * Elasticsearch filter config is a grab bag of shapes: scalars, booleans, and — for several very
     * common filter types (`stop`'s `stopwords`, `synonym`/`synonym_graph`'s `synonyms`,
     * `dictionary_decompounder`'s `word_list`, the `mapping` char filter's `mappings`) — potentially
     * long lists. Two things a naive `(string)`/`json_encode()` cast would get wrong: PHP casts
     * `true`/`false` to `"1"`/`""` (the empty string reads as a missing value, not `false`), and a
     * real-world synonym or stopword list dumped verbatim would turn one debug line into an unreadable
     * multi-KB blob.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatConfigValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            return $this->formatConfigListValue($value);
        }

        return json_encode($value) ?: '';
    }

    /**
     * Only the first few items are shown, with a total count appended when there are more — the config
     * VALUE COUNT (e.g. "312 words") is itself useful debug information even when the words themselves
     * are truncated away.
     *
     * @param array<mixed> $values
     *
     * @return string
     */
    protected function formatConfigListValue(array $values): string
    {
        if ($values === []) {
            return '[]';
        }

        $preview = array_map(
            fn (mixed $value): string => is_scalar($value) ? (string)$value : (json_encode($value) ?: ''),
            array_slice($values, 0, static::CONFIG_LIST_PREVIEW_ITEM_LIMIT),
        );

        $totalCount = count($values);
        if ($totalCount <= static::CONFIG_LIST_PREVIEW_ITEM_LIMIT) {
            return implode(', ', $preview);
        }

        return sprintf('%s, … (%d total)', implode(', ', $preview), $totalCount);
    }
}
