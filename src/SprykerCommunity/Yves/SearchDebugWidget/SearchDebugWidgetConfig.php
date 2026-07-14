<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Yves\SearchDebugWidget;

use Spryker\Shared\SearchElasticsearch\SearchElasticsearchConstants;
use Spryker\Yves\Kernel\AbstractBundleConfig;

class SearchDebugWidgetConfig extends AbstractBundleConfig
{
    /**
     * The query-time boost the catalog search applies to `full-text-boosted`
     * (`full-text-boosted^<value>`, see `CatalogSearchQueryPlugin::createFulltextSearchQuery()`), read
     * from the same shared constant the query side is configured with — displayed on the token-source
     * page so it shows not just *which* field a token matched, but what that match was worth relative to
     * plain `full-text` (which carries no `^` suffix, i.e. Elasticsearch's implicit boost of 1).
     *
     * @api
     *
     * @return int
     */
    public function getFullTextBoostedBoostingValue(): int
    {
        return $this->get(SearchElasticsearchConstants::FULL_TEXT_BOOSTED_BOOSTING_VALUE, 1);
    }
}
