<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget\Resolver;

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
                ? array_map([$this, 'formatValue'], $value)
                : $this->formatValue($value);
        }

        return $formatted;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function formatValue(mixed $value): string
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
