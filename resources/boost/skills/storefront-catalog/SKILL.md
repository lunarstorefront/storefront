---
name: storefront-catalog
description: Fetch and display Lunar Storefront catalog data - products, collections, brands, variants, product options, pricing, and search. Use when building product listing pages, product detail pages, variant selectors, price display, or search results in an app using lunarstorefront/storefront.
---

# Storefront Catalog

## When to use this skill

Use when a consumer app needs catalog data from the `lunarstorefront/storefront` package: product, collection, or brand lookups, product option permutations, variant resolution, pricing, or product search.

## Access pattern

All catalog features hang off the `Storefront` facade, which exposes domain managers:

```php
use Lunar\Storefront\Facades\Storefront;

Storefront::products();    // ProductManager
Storefront::collections(); // CollectionManager
Storefront::brands();      // BrandManager
Storefront::variants();    // VariantManager
Storefront::search();      // SearchManager
Storefront::pricing();     // PricingManager

Storefront::setCurrency('USD'); // sets StorefrontSession + CartSession + Pricing currency
Storefront::setLocale('en');
```

Managers are bound to contracts in `Lunar\Storefront\Contracts\*` — type-hint a contract for injection.

## Products

```php
// Published products only; throws ModelNotFoundException on miss.
// Eager loads: productType.mappedAttributes, media, thumbnail
$product = Storefront::products()->getModelBySlug('acme-widget'); // Lunar\Core\Models\Product

// Options available for this product (only values that have variants)
$options = Storefront::products()->getOptions($product); // Collection<ProductOption>

// Every option-value combination, with stock, purchasability, and a signed hash
$permutations = Storefront::products()->getPermutations($product, $options);
// Collection<Lunar\Storefront\Data\ProductOptionPermutation>
// each: hash, hasVariant, stock, backorder, purchasable, values, valueNames

// Related products as DTOs
use Lunar\Core\Enums\ProductAssociation;
$crossSells = Storefront::products()->getAssociations($product, ProductAssociation::CROSS_SELL);
// Collection<Lunar\Storefront\Data\Product>
```

## Variants

Variant selection round-trips an HMAC-signed hash of the chosen options through the frontend, keyed by the option's `public_id` with the value's `public_id` (the same ids the `ProductOption`, `ProductOptionValue` and `ProductOptionPermutation` DTOs expose). The signature uses the app encryption key (`APP_KEY`). A hash of numeric ids resolves no variant.

```php
$hash = Storefront::variants()->encryptOptions([$option->public_id => $value->public_id]);

$variant  = Storefront::variants()->getProvidedVariant($product, $hash);
// ?ProductVariant — returns the product's first variant when $hash is null

$selected = Storefront::variants()->getSelectedOptions($hash); // Collection [option public id => value public id]
$variant  = Storefront::variants()->getBySku('WIDGET-RED-L');  // ?ProductVariant
```

## Collections

```php
// Root collection by slug, or a child within a parent
$collection = Storefront::collections()->getBySlug('mens');            // ?Lunar\Core\Models\Collection
$collection = Storefront::collections()->getBySlug('mens', 'jackets'); // child 'jackets' under root 'mens'

$breadcrumbs = Storefront::collections()->getBreadcrumbs($collection);
// Collection<Data\Breadcrumb> — label, model, slug

// The tree is a standalone action (NOT on the manager):
use Lunar\Storefront\Actions\Catalog\GetCollectionTree;

$tree = (new GetCollectionTree)->get(group: 'main', maxDepth: 3, eager: ['defaultUrl', 'thumbnail']);
// Collection<Data\Collection>, nested via ->children
```

## Navigation tree

Use for a header or mega menu. `BuildNavigationTree` returns published collections only (hidden or unpublished ones are skipped, with their children), ordered as in the admin tree.

```php
use Lunar\Storefront\Actions\Catalog\BuildNavigationTree;

public function handle(?string $group = null, ?int $depth = null): array; // list<Lunar\Storefront\Data\NavigationItem>

$menu = (new BuildNavigationTree)->handle(); // config defaults
$menu = (new BuildNavigationTree)->handle(group: 'footer', depth: 1);
// NavigationItem: id, name, slug (?string, the default URL), children (NavigationItem[])
```

An unknown group or a depth below 1 returns `[]`. It costs one query for the group plus two per level.

Config (`storefront.navigation`):

- `group`: collection group handle the menu is built from. Default `'main'`.
- `depth`: levels of collections to include. Default `2`.

## Brands

```php
$brand  = Storefront::brands()->getBySlug('acme');  // Brand model, throws on miss
$brands = Storefront::brands()->getPaginated(150);  // LengthAwarePaginator, ordered by name
```

## Search

```php
$results = Storefront::search()->getResults(
    query: 'jacket',
    collection: $collection, // optional — scopes results to the collection
    perPage: 40,
    sort: 'price:asc',       // 'field:asc|desc', default 'relevance:asc'
    filters: [],
);
// Lunar\Search\Data\SearchResults
```

Facets come from `config('lunar.search.facets.'.Product::class)` and are read from the current request automatically (dots in facet keys become underscores in request keys).

### Price filter and page size

`PriceFilter` and `PerPage` turn query-string values into search arguments.

```php
use Lunar\Storefront\Search\PerPage;
use Lunar\Storefront\Search\PriceFilter;

PriceFilter::fromRequest($request); // ?string: '[1000..5000]', '>=1000', '<=5000', or null when no bound is given
PerPage::fromRequest($request);     // int: ?per_page= if it is an offered option, else the default
PerPage::default();                 // int
PerPage::options();                 // list<int> for the listing toolbar

$results = Storefront::search()->getResults(
    query: $q,
    perPage: PerPage::fromRequest($request),
    filters: array_filter(['price' => PriceFilter::fromRequest($request)]), // field => expression
);
```

Bounds are typed in pounds and returned as minor-unit ex-VAT values, so the filter string is for the search engine's price field. With the VAT key set to `inc` the bounds are divided by the standard rate first; a product on a reduced or zero rate can sit slightly either side of an inc-VAT bound. Swapped bounds are reordered; negative values clamp to 0; non-numeric values are ignored. Filters are `field => expression`: use the name of the price field your index exposes in place of `price`.

### Product indexer (prices and collection hierarchy)

Lunar's core indexer does not index prices or collections. Register the package indexer in `config/lunar/search.php` (the `indexers` key is replaced as a whole, so restate the core indexers for your other models):

```php
'indexers' => [
    Product::class => \Lunar\Storefront\Search\ProductIndexer::class,
    // ...the core indexers for the other models
],
```

It adds `min_price` (int, minor units, default currency, quantity 1, sortable and filterable), `min_prices` (currency code => cheapest price), `prices` (the `SearchPriceMap`: `{ default: { GBP: { inclTax: { value, formatted }, exclTax: { value, formatted } } } }`) and `collectionTree` (`lvl0`, `lvl1`, `lvl2` breadcrumbs joined with `' > '`, published collections only; the `collectionTree.lvlN` fields are filterable). Only public prices are indexed (ungrouped and the default customer group). Declare `min_price`, `min_prices`, `prices` and `collectionTree.lvl0..2` in your Typesense schema (with nested fields enabled). The class is not final, so a store can extend it.

Config (`storefront.search`):

- `per_page.default`: `40`
- `per_page.options`: `[40, 80, 120]`
- `price_filter.min_key`: `'price_min'`
- `price_filter.max_key`: `'price_max'`
- `price_filter.vat_key`: `'price_vat'` (request key; the value `inc` means bounds include VAT)
- `price_filter.vat_rate`: `20` (percent)

## Pricing

```php
$pricing = Storefront::pricing()->getPricing($variant, quantity: 3);
// ?Lunar\Core\DataObjects\PricingResponse — null on failure (the exception is reported)

$price = Storefront::pricing()->getQuantifiedPrice($pricing, 3);
// ?Data\Price — ex/inc tax multiplied by quantity, with formatted string variants

$breaks = Storefront::pricing()->mapPriceBreaks($pricing);
// Collection<Data\PriceBreak> — price, lowerLimit, upperLimit
```

## Stock state

Use on a product page to show in stock, low stock, backorder or out of stock. `GetProductStock` reads one variant: the one you pass, otherwise the product's lowest-id variant. A product with no variants is `out_of_stock`.

```php
use Lunar\Storefront\Actions\Catalog\GetProductStock;

public function handle(Product $product, ?ProductVariant $variant = null): Lunar\Storefront\Data\ProductStock;

$stock = (new GetProductStock)->handle($product, $variant);
// ProductStock: state ('in_stock' | 'low_stock' | 'backorder' | 'out_of_stock'),
//   count (?int, only when show_quantity is on), showQuantity (bool),
//   leadTimeDays (?int, backorder lead time), maxQuantity (?int, null means no practical limit)
```

State: `out_of_stock` when the variant cannot be fulfilled at quantity 1; `in_stock` when available stock is above `low_threshold`; `low_stock` above zero; otherwise `backorder`. `maxQuantity` is null for a variant with an always-sell policy or an inventory total above `practical_limit`.

Config (`storefront.stock`):

- `low_threshold`: `5` (stock at or below this, and above zero, is low stock)
- `show_quantity`: `false` (expose the unit count)
- `lead_time_attribute`: `null` (product attribute handle holding backorder lead time in days)
- `practical_limit`: `999` (above this the cart limit is an allowance, not real stock)

## What returns models vs DTOs

- Eloquent models: `getModelBySlug`, `getBySlug` (collection/brand), `getBySku`, `getProvidedVariant`, `getOptions`.
- `Lunar\Storefront\Data\*` DTOs: `getPermutations`, `getAssociations`, `getBreadcrumbs`, `getQuantifiedPrice`, `mapPriceBreaks`, `GetCollectionTree`, `BuildNavigationTree` (`NavigationItem`), `GetProductStock` (`ProductStock`).

Convert models to DTOs for Inertia props with the DTO factories, e.g. `Lunar\Storefront\Data\Product::from($product)`. DTO relations are `Lazy` — they only serialize when the Eloquent relation is eager-loaded.

## Gotchas

- `products()->getModelBySlug` and `brands()->getBySlug` throw `ModelNotFoundException`; `collections()->getBySlug` returns null.
- `getModelBySlug` only returns products in the `Published` state.
- `PricingManager::mapPriceBreaks()` exists on the concrete manager but not on the `PricingManager` contract — call it via the facade, not a contract type-hint.
- `GetCollectionTree` is not exposed on any manager; instantiate the action directly.

The UK postcode area lists in `Lunar\Storefront\Shipping\UkPostcodes` (`mainland()`, `highlandsAndIslands()`, `excluded()`) mainly back the `uk` install preset's shipping zones; reuse them if a store needs the same zone definitions.
