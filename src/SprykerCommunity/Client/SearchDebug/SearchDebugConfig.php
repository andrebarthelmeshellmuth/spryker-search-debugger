<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug;

use Spryker\Client\Kernel\AbstractBundleConfig;

class SearchDebugConfig extends AbstractBundleConfig
{
    /**
     * Source identifier of the page search index — the index the product catalog search runs against.
     * "page" is Spryker's standard identifier for it; override this config in your project
     * (`Pyz\Client\SearchDebug\SearchDebugConfig`) if your installation names it differently.
     *
     * @var string
     */
    protected const SOURCE_IDENTIFIER_PAGE = 'page';

    /**
     * @api
     */
    public function getPageSourceIdentifier(): string
    {
        return static::SOURCE_IDENTIFIER_PAGE;
    }
}
