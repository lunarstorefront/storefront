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

Variant selection round-trips an HMAC-signed hash of the chosen options (`[product_option_id => value_id]`) through the frontend. The signature uses the app encryption key (`APP_KEY`).

```php
$hash = Storefront::variants()->encryptOptions([$optionId => $valueId]);

$variant  = Storefront::variants()->getProvidedVariant($product, $hash);
// ?ProductVariant — returns the product's first variant when $hash is null

$selected = Storefront::variants()->getSelectedOptions($hash); // Collection [optionId => valueId]
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

## Pricing

```php
$pricing = Storefront::pricing()->getPricing($variant, quantity: 3);
// ?Lunar\Core\DataObjects\PricingResponse — null on failure (the exception is reported)

$price = Storefront::pricing()->getQuantifiedPrice($pricing, 3);
// ?Data\Price — ex/inc tax multiplied by quantity, with formatted string variants

$breaks = Storefront::pricing()->mapPriceBreaks($pricing);
// Collection<Data\PriceBreak> — price, lowerLimit, upperLimit
```

## What returns models vs DTOs

- Eloquent models: `getModelBySlug`, `getBySlug` (collection/brand), `getBySku`, `getProvidedVariant`, `getOptions`.
- `Lunar\Storefront\Data\*` DTOs: `getPermutations`, `getAssociations`, `getBreadcrumbs`, `getQuantifiedPrice`, `mapPriceBreaks`, `GetCollectionTree`.

Convert models to DTOs for Inertia props with the DTO factories, e.g. `Lunar\Storefront\Data\Product::from($product)`. DTO relations are `Lazy` — they only serialize when the Eloquent relation is eager-loaded.

## Gotchas

- `products()->getModelBySlug` and `brands()->getBySlug` throw `ModelNotFoundException`; `collections()->getBySlug` returns null.
- `getModelBySlug` only returns products in the `Published` state.
- `PricingManager::mapPriceBreaks()` exists on the concrete manager but not on the `PricingManager` contract — call it via the facade, not a contract type-hint.
- `GetCollectionTree` is not exposed on any manager; instantiate the action directly.
