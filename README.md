# Spryker Search DevTools

Developer tools for inspecting, debugging and understanding OpenSearch/Elasticsearch queries in Spryker.
Search Debug helps Search Engineers explain ranking decisions—quickly enough that they can confidently answer the business question: "Why did this product rank above that one?"

## What does this do?

A permission-gated user browsing the storefront search results gets a per-product overlay with the real
Elasticsearch `_score`, which query tokens matched, and — one click deeper — the exact BM25 boost/idf/tf
numbers behind each match, pinned open for comparing two products side by side:

![The SRP score overlay, pinned open, showing matched tokens with their BM25 breakdown and the final score used for ranking](docs/screenshots/srp-overlay.png)

No more "because Elasticsearch said so" — every number on the page traces back to a real, inspectable part
of the query.

## Status

🚧 Early development — the first tool (search relevance debugging, including per-token analysis-path
visualization) is functional; more are planned.

## Search Debug — Spryker Community Extension

Search relevance debugging for Spryker storefronts, on top of the standard Elasticsearch/OpenSearch
catalog search:

- **SRP score overlay** — permission-gated customers see, per product on the search results page, the raw
  Elasticsearch `_score`, the analyzer tokens of their query, and which tokens matched with which score
  contribution (parsed from the Elasticsearch `explain` tree into a compact per-token breakdown). A matched
  token scored by BM25Similarity expands into its own boost/idf/tf breakdown (document frequency, term
  frequency, field length vs. average field length — every number BM25 actually computes with), collapsed
  behind its own toggle so the headline total stays the default view. The overlay stays open on click (a
  pin-toggle button, independent of continued hover) for copying values or comparing two products side by
  side.
- **Token-source page** — a magnifier next to each matched token opens a page that attributes the token
  back to the raw product fields it was indexed from (name, SKU, variants, descriptions, categories,
  merchant name). It reads the product's **real indexed document** and analyzes its elements with the
  **index-time analyzer**, so prefix/ngram matches (e.g. searching `öl` matching *Ölpapier*) and
  searchable-attribute contributions (Zed → Search Preferences) are attributed correctly — including
  values no known source claims, which are shown honestly as "other indexed value".

  ![The token-source page: one tier per searched field, each matched fragment highlighted with a link to its analysis path, an unclaimed value labeled honestly by its real attribute key ("brand")](docs/screenshots/token-source-page.png)
- **Analysis-path page** — a second magnifier next to each matched fragment on the token-source page opens
  a page showing exactly how that raw text became the matched token: one box per analyzer stage (char
  filters, the tokenizer, every token filter, in chain order), connected by the ES operation that produced
  each one — e.g. `Ölpapier` → *filter: lowercase* → `ölpapier` → *filter: fulltext_index_ngram_filter* →
  `öl`. Always a straight line, never a tree: even a filter that fans one token into several (ngram,
  decompounding, synonyms) only contributes the ONE step that actually led to the matched token — the
  path is reconstructed by walking backward through Lucene's own offsets (which stay relative to the
  original text across every stage), not by inspecting filter types, so it works for any analyzer chain.
  Each operation also shows that filter's own configuration, read live from the index's analysis settings
  (e.g. `filter: fulltext_index_ngram_filter` → `edge_ngram (min_gram: 2, max_gram: 20)`) — built-in
  components used by name only (`lowercase`, `standard`) show no definition, since nothing was customized.
  Every step is colored by its own exact text, cycling a fixed palette — the SAME text anywhere in the
  path gets the SAME color, so a color CHANGE between neighboring steps is itself the visual tell that a
  filter actually transformed the text (e.g. a synonym injecting a different word), not just decoration.

  ![The analysis-path page: "trolley" traced stage by stage until the fulltext_synonyms filter injects "handcart" — the color change from green to orange is the visual tell](docs/screenshots/analysis-path-page.png)
- **Component-config page** — when a filter's configuration is too long to show inline (a `stop`/`synonym`
  filter's word list can run into the hundreds), the analysis-path page's definition line shows a preview
  plus a "view full definition" link instead of dumping everything into one line. It opens a new tab
  showing that ONE component's config in full, re-fetched server-side by kind+name — nothing is smuggled
  through the URL.
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

In your search view (e.g. a project `search.twig`), map the raw `_view.searchDebug.*` result data (nested
under the `SearchDebugResultFormatterPlugin::NAME` key) into flat `data.*` entries alongside your other
view data:

```twig
{% define data = {
    {# ...your existing entries... #}

    searchDebugTokens: _view.searchDebug.tokens | default([]),
    searchDebugProducts: _view.searchDebug.products | default([]),
    searchDebugFieldBoosts: _view.searchDebug.fieldBoosts | default([]),
    searchDebugTokenColors: _view.searchDebugTokenColors | default([]),
} %}
```

Then render the query-token headline:

```twig
{% include molecule('search-debug-tokens', 'SearchDebugWidget') with {
    data: {
        tokens: data.searchDebugTokens,
        tokenColors: data.searchDebugTokenColors,
    },
} only %}
```

In your product-grid template (e.g. `page-layout-catalog.twig`), render the per-product overlay inside
the product loop — `fieldBoosts` is the query's real, live field=>boost pairs (captured by
`QueryFieldBoostReader`, e.g. `{'full-text': 1, 'full-text-boosted': 5}`), forwarded through the
per-token link so the token-source page shows however many fields your query actually searched, at their
real boost values, with no hardcoded field count or boost assumption:

```twig
{% set productSearchDebugInfo = (data.searchDebugProducts | default([]))[product.id_product_abstract] | default([]) %}
{% if productSearchDebugInfo is not empty %}
    {% include molecule('search-debug-product-info', 'SearchDebugWidget') with {
        data: {
            debugInfo: productSearchDebugInfo,
            tokenColors: data.searchDebugTokenColors | default([]),
            productAbstractSku: product.abstract_sku,
            fieldBoosts: data.searchDebugFieldBoosts | default([]),
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

Copy the rows from this package's [`data/glossary.csv`](data/glossary.csv) into your project's own
`glossary.csv` (e.g. `data/import/common/common/glossary.csv`) — every `search_debug.*` key the package
references, already translated for `en_US`/`de_DE`. Edit the translated text or add further locales as
your project needs; nothing about the KEYS themselves is project-specific. Then re-run:

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
  single client-layer gate). It also captures the query's real `multi_match` field=>boost pairs
  (`QueryFieldBoostReader`) — whatever fields your query actually searches, at whatever boost, nothing
  hardcoded — for `SearchDebugResultFormatterPlugin` to include in its output.
- `SearchDebugResultFormatterPlugin` parses each hit's explain tree (`ExplanationParser`, a recursive
  shape-matching walker with an honest fallback for unknown node shapes) into per-token score
  contributions. Per-field weights for the same term are combined via whichever mode the explain tree's
  own nodes indicate — max/dis_max or sum/bool-should — detected from the node descriptions, not assumed;
  `function_score` boost-function contributions (field_value_factor, decay functions, script_score) get
  their own bucket, separate from other score contributions.
- The token-source page (`SearchDebugWidget` Yves module) reads the product's indexed document via the
  synchronization-service document id, and tests every element of every field the query searched (however
  many that is — see `QueryFieldBoostReader` above) with the index-time analyzer (`_analyze` with
  `explain: true`, offsets included). Each element is labeled by matching it first against the known
  NAMED source values (title, SKU, description, category, merchant name, ...), then against the product's
  own searchable attribute values (labeled with the real attribute key, e.g. "brand") — anything neither
  identifies still shows up, under a generic "other indexed value" label. Tiers render sorted by boost
  descending, with the real, live boost value shown next to each.
- The analysis-path page (`AnalysisPathController`/`AnalysisPathResolver`) re-analyzes one matched
  fragment's raw text with `_analyze?explain=true`, but reads the FULL per-stage breakdown
  (`SearchStringAnalyzer::getAnalysisStages()`/`SearchDebugClientInterface::getTextAnalysisStages()`) —
  char filters, the tokenizer, and every token filter — instead of collapsing straight to the final
  tokens. `AnalysisPathResolver` then walks that stage list BACKWARD from the already-known matched
  token's offset, picking at each step the one earlier-stage token whose offset range contains the
  current one. Lucene offsets are always relative to the original text, never re-based per stage, so
  containment alone determines lineage — no filter-specific logic needed, and no branching: only the one
  lineage that produced the matched token is ever visited, regardless of how many sibling tokens an
  earlier stage's token fanned out into.
- Each stage's operation is enriched with its own configuration by `ComponentDefinitionFormatter`, which
  looks the component up by name in the live index's analysis settings (`IndexSchemaReader::findComponent()`,
  fed by `IndexSchemaMapper` parsing the `tokenizer`/`filter`/`char_filter` blocks of `_settings.analysis`
  — the SAME live schema call already used to resolve analyzer names, so this is not extra I/O). PHP's
  `true`/`false` → `"1"`/`""` string-cast quirk is handled explicitly, and a config list longer than 5
  items is shown as a preview with a total count, flagged `truncated` rather than dumped verbatim — a real
  `synonym`/`stop` filter's word list can run into the hundreds. When a stage IS truncated,
  `ComponentConfigController` re-fetches that same component (`SearchDebugClientInterface::getComponentConfig()`)
  and `ComponentConfigFormatter` renders its config in full for the component-config page — same two shape
  hazards handled again, just without the length cap, since showing everything is the whole point there.

## Extending the overlay

Other packages can contribute additional score sections to the per-product SRP overlay via
`SprykerCommunity\Client\SearchDebug\Dependency\Plugin\ProductDebugDataExpanderPluginInterface`
(registered by overriding `SearchDebugDependencyProvider::getProductDebugDataExpanderPlugins()` on
project level). Each plugin receives the parsed per-hit debug data plus the raw document `_source`
and may append generically-rendered sections (title, `label: calculation = value` lines, a summary
line and a free-text formula line) — e.g.
[spryker-community/search-ranking](https://github.com/andrebarthelmeshellmuth/spryker-search-ranking)
explains its business-signal `function_score` this way.

For a `function_score`-wrapped query the explain parser additionally exposes the WRAPPED query's own
relevance as `queryScore` (shown as "Text match score", the number the matched-token breakdown adds
up against), suppresses Elasticsearch's float-max `maxBoost` sentinel from the output, and the
overlay closes with the final `_score` actually used for ranking.

### Display precision

`SprykerCommunity\Shared\SearchDebug\SearchDebugConfig::SCORE_DECIMAL_PLACES` (default **3**) is the
single constant controlling how many decimal places EVERY number in the overlay is rounded and
displayed to — the final `_score`, matched-token weights, other contributions, and any section a
`ProductDebugDataExpanderPluginInterface` plugin contributes. Consuming plugins (e.g.
spryker-community/search-ranking's business-signal breakdown) read this same constant for the
calculation/formula strings they pre-build, so the whole overlay always shows one consistent
precision — there is no second place to keep in sync.

Rounding happens **only** at this display layer. No business-logic class in this package, or in a
contributing plugin, rounds a value before it reaches the twig template (or a pre-built display
string) — the full-precision float is always what gets computed with; this constant purely controls
how the number is shown.

## Limitations

- **Assumes Spryker's default single-resource catalog search model.** `TokenSourceResolver` always resolves
  a SKU to a `product_abstract` and reads the `product_abstract` search resource's document. Shops with a
  different catalog/search topology — a tabbed abstract/concrete search, an additional "single" product
  type with no abstract, or multiple search resources — need to adapt the resource name and SKU→ID lookup
  to their own model; this is a structural assumption the package does not attempt to generalize away.
- **The field→tier mapping is a basic shop's default, not a discovered fact.** `TokenSourceResolver::SOURCE_DEFINITIONS`
  mirrors a basic Spryker shop's default `ProductPageSearchDependencyProvider`
  wiring (title/SKU/direct category/merchant name → `full-text-boosted`; concrete names/SKUs/descriptions
  and indirect categories → `full-text`). Nothing in the indexed document or the live cluster records which
  *source field* a value in `full-text`/`full-text-boosted` came from (that's the whole reason this feature
  exists), so if your project registers different map-expander plugins, or moves a field between tiers,
  edit `SOURCE_DEFINITIONS` to match your project's actual wiring — that's the one place to check.

  The real fix for this limitation would live upstream, not in this package: instead of flattening every
  contributed value into a bare `["val1", "val2", ...]` array (which is what destroys the source-field
  information in the first place), the indexing pipeline could export each value tagged with its own
  origin field, e.g. `[{"field": "name", "value": "val1"}, {"field": "sku", "value": "val2"}, ...]`. We
  looked at this and deliberately didn't go there — the cost isn't just the obvious one (a tagged
  structure is heavier on disk than a flat string array, once per product, across the whole catalog).
  The bigger issue is that `full-text`/`full-text-boosted` are plain `text` fields today specifically so a
  simple `multi_match` can query them directly; tagged per-field values would need to be mapped as
  `nested` (or dynamically-mapped `object`) fields instead, and `nested` queries are a genuinely more
  expensive query shape in Elasticsearch (an extra join against hidden child documents per query, on
  every storefront search, not just the rare debug-permitted one). It would also change how BM25's
  field-length normalization is computed (today it's over the whole flattened field; per-tagged-value
  would need its own model) and require a full catalog reindex to adopt. That's real risk to the
  production relevance engine every shopper depends on, taken on to make an occasional debugging lookup
  slightly more precise — not something a debug-only add-on package should be pushing a project toward.
- **Category pages don't get debug output — deliberately, not because they can't.** The sample
  `CatalogController` (step 5) only sets `isSearchDebugContext` inside `executeFulltextSearchAction()`,
  even though `reduceRestrictedParameters()` — the method that actually turns that flag into the request
  parameter the query plugins read — is shared with the category listing action underneath it. A category
  page CAN carry a free-text `q` too (Spryker lets a customer search *within* a category), but most
  category page loads have no query string at all, so there'd usually be nothing meaningful to explain;
  enabling it there would mean paying Elasticsearch's real `explain` cost on every category page view for
  output that's thrown away most of the time. This is an easy, mechanical extension if you want it: the
  per-product overlay itself already lives in the SHARED `page-layout-catalog.twig` (search and category
  pages both render through it), so it starts appearing automatically once debug data exists — the only
  change needed is overriding `executeIndexAction()` the same way `executeFulltextSearchAction()` already
  is, setting the same `isSearchDebugContext` flag at the top.

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
