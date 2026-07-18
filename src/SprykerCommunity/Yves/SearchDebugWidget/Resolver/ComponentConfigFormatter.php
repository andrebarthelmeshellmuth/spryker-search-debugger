<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

use SprykerCommunity\Shared\SearchDebug\Format\ConfigValueScalarFormatter;

class ComponentConfigFormatter implements ComponentConfigFormatterInterface
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, string|array<string>>
     */
    public function format(array $config): array
    {
        $formatted = [];

        foreach ($config as $key => $value) {
            $formatted[$key] = is_array($value)
                ? array_map([ConfigValueScalarFormatter::class, 'format'], $value)
                : ConfigValueScalarFormatter::format($value);
        }

        return $formatted;
    }
}
