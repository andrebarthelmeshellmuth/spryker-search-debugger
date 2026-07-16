<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Analyzer;

use Generated\Shared\Transfer\SearchAnalysisComponentTransfer;

interface ComponentDefinitionFormatterInterface
{
    /**
     * Formats a named tokenizer/filter/char_filter definition into a short, human-readable `label`, e.g.
     * `edge_ngram (min_gram: 2, max_gram: 20)`, or just the type when it has no further config.
     * `null` in, `null` out — the component wasn't found (a built-in Elasticsearch component used by
     * name only, nothing custom configured for it).
     *
     * `truncated` is true when `label` left something out (a long config list capped to a preview, or
     * the whole line capped by an overall character limit) — a signal for a UI layer to offer a "view
     * full definition" link to the untruncated config, re-fetched server-side by this component's kind
     * + name.
     *
     * @param \Generated\Shared\Transfer\SearchAnalysisComponentTransfer|null $component
     *
     * @return array{label: string, truncated: bool}|null
     */
    public function format(?SearchAnalysisComponentTransfer $component): ?array;
}
