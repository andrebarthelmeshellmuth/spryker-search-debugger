<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;
use SprykerCommunity\Shared\SearchDebug\Format\ConfigValueScalarFormatter;

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

        foreach ($component->getConfigOrFail() as $key => $value) {
            $parameters[] = $key . ': ' . $this->formatConfigValue($value);

            if (!is_array($value) || count($value) <= static::CONFIG_LIST_PREVIEW_ITEM_LIMIT) {
                continue;
            }

            $truncated = true;
        }

        $label = $parameters === []
            ? $component->getTypeOrFail()
            : sprintf('%s (%s)', $component->getTypeOrFail(), implode(', ', $parameters));

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
     */
    protected function formatConfigValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->formatConfigListValue($value);
        }

        return ConfigValueScalarFormatter::format($value);
    }

    /**
     * A short list (at or under the preview limit) is shown in full — genuinely useful debug information
     * at that size. Past the limit, just the COUNT is shown ("(312 total)"), not a handful of example
     * items — a partial item preview reads as if those specific words were somehow special/representative,
     * when they're really just whatever happened to be first in the config; the count alone says exactly
     * as much without that false implication.
     *
     * @param array<mixed> $values
     */
    protected function formatConfigListValue(array $values): string
    {
        if ($values === []) {
            return '[]';
        }

        $totalCount = count($values);

        if ($totalCount <= static::CONFIG_LIST_PREVIEW_ITEM_LIMIT) {
            return implode(', ', array_map(ConfigValueScalarFormatter::format(...), $values));
        }

        return sprintf('(%d total)', $totalCount);
    }
}
