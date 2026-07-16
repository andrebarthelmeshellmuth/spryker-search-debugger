<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

interface ComponentConfigFormatterInterface
{
    /**
     * Formats a raw component config (as returned by
     * `SprykerCommunity\Client\SearchDebug\SearchDebugClientInterface::getComponentConfig()`) into
     * display-safe strings, for the "full definition" page — the untruncated counterpart of
     * `SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatterInterface`'s short inline
     * preview: every value is shown here, none of them shortened, but the same two shape hazards apply
     * (PHP casts `true`/`false` to `"1"`/`""`; a nested array can't print directly in Twig at all).
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, string|array<string>>
     */
    public function format(array $config): array;
}
