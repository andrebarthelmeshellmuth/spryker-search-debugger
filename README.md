# Spryker Search DevTools

Developer tools for inspecting, debugging and understanding OpenSearch/Elasticsearch queries in Spryker.

## Status

🚧 Early development — the first tool (search relevance debugging) is functional; more are planned
(analysis-pipeline visualization is next).

## Search Debug — Spryker Community Extension

Search relevance debugging for Spryker storefronts, on top of the standard Elasticsearch/OpenSearch
catalog search:

- **SRP score overlay** — permission-gated customers see, per product on the search results page, the raw
  Elasticsearch `_score`, the analyzer tokens of their query, and which tokens matched with which score
  contribution (parsed from the Elasticsearch `explain` tree into a compact per-token breakdown).
- **Token-source page** — a magnifier next to each matched token opens a page that attributes the token
  back to the raw product fields it was indexed from (name, SKU, variants, descriptions, categories,
  merchant name). It reads the product's **real indexed document** and analyzes its elements with the
  **index-time analyzer**, so prefix/ngram matches (e.g. searching `öl` matching *Ölpapier*) and
  searchable-attribute contributions (Zed → Search Preferences) are attributed correctly — including
  values no known source claims, which are shown honestly as "other indexed value".
- **Zero analyzer configuration** — analyzer names are resolved from the live index's mapping
  (`analyzer` / `search_analyzer` of the `full-text` field, with Elasticsearch's own fallback rules), not
  from config, so the package works with any `page.json` customization or the vanilla core schema.

Access is controlled through Spryker's Company Role permission system: only customers whose role holds
the `SeeSearchDebugInfoPermissionPlugin` permission see any debug output. The permission is enforced in
the Client layer, so it also covers non-Yves entry points (e.g. the Glue catalog-search resource).

## Requirements

- Spryker B2B/B2C/Marketplace shop on the `spryker/search-elasticsearch` stack (Elasticsearch 7 / OpenSearch 1)
- PHP >= 8.2

## Installation

### 1. Install the package

```bash
composer require spryker-community/search-debug
```

### 2. Register the core namespace

Add `SprykerCommunity` to the core namespaces in `config/Shared/config_default.php`:

```php
$config[KernelConstants::CORE_NAMESPACES] = [
    'SprykerShop',
    'SprykerEco',
    'Spryker',
    'SprykerSdk',
    'SprykerFeature',
    'SprykerCommunity', // <- add this line
];
```

This makes the package's modules resolvable like core modules — and, like core modules, extendable from
your project namespace (e.g. `Pyz\Client\SearchDebug\SearchDebugConfig`).

### 3. Generate transfers and clear caches

```bash
vendor/bin/console transfer:generate
vendor/bin/console cache:empty-all
```

### 4. Register the plugins

`src/Pyz/Client/Catalog/CatalogDependencyProvider.php` — enable `explain` and the debug result data on
the catalog search query:

```php
use SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugQueryExpanderPlugin;
use SprykerCommunity\Client\SearchDebug\Plugin\Catalog\SearchDebugResultFormatterPlugin;

    protected function createCatalogSearchQueryExpanderPlugins(): array
    {
        return [
            // ... existing plugins, before FacetQueryExpanderPlugin
            new SearchDebugQueryExpanderPlugin(),
            new FacetQueryExpanderPlugin(),
        ];
    }

    protected function createCatalogSearchResultFormatterPlugins(): array
    {
        return [
            // ... existing plugins
            new SearchDebugResultFormatterPlugin(),
        ];
    }
```

`src/Pyz/Zed/Permission/PermissionDependencyProvider.php` **and**
`src/Pyz/Client/Permission/PermissionDependencyProvider.php` — make the permission assignable and
checkable:

```php
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;

    protected function getPermissionPlugins(): array
    {
        return [
            // ... existing plugins
            new SeeSearchDebugInfoPermissionPlugin(),
        ];
    }
```

`src/Pyz/Yves/Router/RouterDependencyProvider.php` — register the token-source route:

```php
use SprykerCommunity\Yves\SearchDebugWidget\Plugin\Router\SearchDebugWidgetRouteProviderPlugin;

    protected function getRouteProvider(): array
    {
        return [
            // ... existing plugins
            new SearchDebugWidgetRouteProviderPlugin(),
        ];
    }
```

### 5. Extend your catalog controller

The catalog controller is the single place where the permission decides whether a request produces debug
output — the decision travels to the query plugins as a server-set request parameter that cannot be
spoofed from the URL. Extend your `src/Pyz/Yves/CatalogPage/Controller/CatalogController.php`:

```php
use SprykerCommunity\Shared\SearchDebug\Plugin\SeeSearchDebugInfoPermissionPlugin;
use SprykerCommunity\Shared\SearchDebug\SearchDebugConfig;

class CatalogController extends SprykerShopCatalogController
{
    protected const VIEW_DATA_SEARCH_DEBUG_TOKEN_COLORS = 'searchDebugTokenColors';

    protected bool $isSearchDebugContext = false;

    protected function executeFulltextSearchAction(Request $request): array
    {
        $this->isSearchDebugContext = $this->can(SeeSearchDebugInfoPermissionPlugin::KEY);

        $searchResults = parent::executeFulltextSearchAction($request);

        $queryTokens = $searchResults[SearchDebugConfig::SEARCH_RESULT_KEY][SearchDebugConfig::KEY_TOKENS] ?? [];
        $searchResults[static::VIEW_DATA_SEARCH_DEBUG_TOKEN_COLORS] = $this->getSearchDebugTokenColorClasses($queryTokens);

        return $searchResults;
    }

    protected function reduceRestrictedParameters(array $parameters): array
    {
        unset($parameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG]);

        $parameters = parent::reduceRestrictedParameters($parameters);

        if ($this->isSearchDebugContext) {
            $parameters[SearchDebugConfig::REQUEST_PARAM_SEARCH_DEBUG] = true;
        }

        return $parameters;
    }

    protected function getSearchDebugTokenColorClasses(array $queryTokens): array
    {
        $colorClassByToken = [];
        foreach (array_values($queryTokens) as $index => $token) {
            $colorClassByToken[$token] = sprintf(
                SearchDebugConfig::TOKEN_COLOR_CLASS_PATTERN,
                ($index % SearchDebugConfig::TOKEN_COLOR_CLASS_COUNT) + 1,
            );
        }

        return $colorClassByToken;
    }
}
```

(Requires `use Spryker\Yves\Kernel\PermissionAwareTrait;` in the class if not present.)

### 6. Hook up the storefront templates

In your search view (e.g. a project `search.twig`), render the query-token headline:

```twig
{% include molecule('search-debug-tokens', 'SearchDebugWidget') with {
    data: {
        tokens: _view.searchDebug.tokens | default([]),
        tokenColors: _view.searchDebugTokenColors | default([]),
    },
} only %}
```

In your product-grid template (e.g. `page-layout-catalog.twig`), render the per-product overlay inside
the product loop:

```twig
{% set productSearchDebugInfo = (data.searchDebugProducts | default([]))[product.id_product_abstract] | default([]) %}
{% if productSearchDebugInfo is not empty %}
    {% include molecule('search-debug-product-info', 'SearchDebugWidget') with {
        data: {
            debugInfo: productSearchDebugInfo,
            tokenColors: data.searchDebugTokenColors | default([]),
            productAbstractSku: product.abstract_sku,
        }
    } only %}
{% endif %}
```

### 7. Frontend build

Add the community vendor directory to your Yves builder so the package's components compile. In
`frontend/settings.js`, add to `paths`:

```js
community: './vendor/spryker-community',
```

...mirror it in the per-theme path mapping, and add `join(globalSettings.context, paths.community)` to
`find.componentEntryPoints.dirs`. Then:

```bash
yarn yves
```

### 8. Glossary entries

Add the `search_debug.*` translations to your glossary data import (see
`data/import/.../glossary.csv` in the reference integration) and re-run:

```bash
vendor/bin/console data:import glossary
```

### 9. Grant the permission

In Zed → Customer → Company Roles, assign the **SeeSearchDebugInfoPermissionPlugin** permission to a
dedicated role (recommended: a separate "Search Admin" role rather than widening an existing admin
role), and assign that role to the users who should see debug output.

## How it works

- `SearchDebugQueryExpanderPlugin` switches Elasticsearch inline `explain` on — only when the request
  was flagged by the controller AND the customer holds the permission (`SearchDebugAccessChecker`, the
  single client-layer gate).
- `SearchDebugResultFormatterPlugin` parses each hit's explain tree (`ExplanationParser`, a recursive
  shape-matching walker with an honest fallback for unknown node shapes) into per-token score
  contributions.
- The token-source page (`SearchDebugWidget` Yves module) reads the product's indexed document via the
  synchronization-service document id, tests every `full-text`/`full-text-boosted` element with the
  index-time analyzer (`_analyze` with `explain: true`, offsets included), and labels each element by
  matching it against the known source values — grouped under the two ES fields with their live query
  boosts.

## Testing

The package ships Codeception suites under `tests/SprykerCommunityTest/`. They currently run inside a
host shop (they use the host's test bootstrap and, for the analyzer tests, a live Elasticsearch):

```bash
vendor/bin/codecept build -c vendor/spryker-community/search-debug/tests/SprykerCommunityTest/Client/SearchDebug
vendor/bin/codecept run -c vendor/spryker-community/search-debug/tests/SprykerCommunityTest/Client/SearchDebug
vendor/bin/codecept run -c vendor/spryker-community/search-debug/tests/SprykerCommunityTest/Yves/SearchDebugWidget
```

Note: the suites reference the host's `\PyzTest\Shared\Testify\Helper\Environment` helper; a standalone
CI bootstrap is on the roadmap.

## License

MIT — see [LICENSE](LICENSE).
