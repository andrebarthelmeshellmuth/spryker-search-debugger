<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchDebug\Format;

/**
 * Formats one non-list Elasticsearch analysis-component config value for display — shared by
 * {@see \SprykerCommunity\Client\SearchDebug\Analyzer\ComponentDefinitionFormatter} (Client layer,
 * truncated inline preview) and {@see \SprykerCommunity\Yves\SearchDebugWidget\Resolver\ComponentConfigFormatter}
 * (Yves layer, full untruncated display), so both stay consistent for the same kind of value instead of
 * two independently-maintained copies. A naive `(string)`/`json_encode()` cast would get one thing wrong:
 * PHP casts `true`/`false` to `"1"`/`""` — the empty string reads as a missing value, not `false`.
 */
class ConfigValueScalarFormatter
{
    /**
     * @param mixed $value
     */
    public static function format(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return json_encode($value) ?: '';
    }
}
