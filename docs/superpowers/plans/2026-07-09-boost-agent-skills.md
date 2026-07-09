# Boost Agent Skills Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Laravel Boost v2 auto-discoverable agent skills and a core guideline inside the `lunarstorefront/storefront` composer package.

**Architecture:** Static resource files at `resources/boost/skills/{name}/SKILL.md` and `resources/boost/guidelines/core.blade.php` — Boost v2 in the consumer app discovers them by convention on `boost:install` / `boost:update --discover`. No service provider changes, no publish tags. A Pest test validates frontmatter and enforces content constraints.

**Tech Stack:** Markdown (Agent Skills format), Blade, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-09-boost-agent-skills-design.md`.
- Skill folder path convention (exact): `resources/boost/skills/{skill-name}/SKILL.md`.
- Every SKILL.md frontmatter: `name` MUST equal its folder name; `description` MUST be non-empty and phrased as an activation trigger.
- The word "Stripe" MUST NOT appear in any file under `resources/boost/` (checkout is deferred; storefront is moving to a checkout-session approach).
- No `storefront-checkout` skill in this plan.
- All code snippets in skills MUST match the actual package source APIs (they were verified against `src/` on 2026-07-09; copy them verbatim from this plan).
- Variant option hashing uses the Laravel app encryption key (`Crypt::getKey()`); `config('storefront.key')` is currently unused by package code. Do not claim otherwise in docs.
- Work happens on branch `feat/boost-agent-skills` (already created; spec committed).
- Test command: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`.

---

### Task 1: Boost resources test + storefront-catalog skill

**Files:**
- Create: `tests/Unit/BoostResourcesTest.php`
- Create: `resources/boost/skills/storefront-catalog/SKILL.md`

**Interfaces:**
- Produces: `tests/Unit/BoostResourcesTest.php` with a `boost skill is valid` test using a Pest dataset array named `$boostSkills` — later tasks append their skill name to this array.
- Produces: `resources/boost/skills/` directory that later tasks add sibling skill folders to.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/BoostResourcesTest.php`:

```php
<?php

$boostSkills = [
    'storefront-catalog',
];

it('ships a valid boost skill', function (string $skill) {
    $file = dirname(__DIR__, 2)."/resources/boost/skills/{$skill}/SKILL.md";

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect(preg_match('/\A---\R(.*?)\R---\R/s', $content, $frontmatter))->toBe(1);

    expect(preg_match('/^name:\s*(\S+)\s*$/m', $frontmatter[1], $name))->toBe(1);
    expect($name[1])->toBe($skill);

    expect(preg_match('/^description:\s*(\S.*)$/m', $frontmatter[1], $description))->toBe(1);
})->with($boostSkills);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: FAIL — `ships a valid boost skill with ('storefront-catalog')` fails on the `file_exists` expectation (file does not exist yet).

- [ ] **Step 3: Create the skill file**

Create `resources/boost/skills/storefront-catalog/SKILL.md` with exactly this content:

````markdown
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

// Every option-value combination, with stock, purchasability, and an encrypted hash
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
````

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: PASS — 1 test (1 dataset case).

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/BoostResourcesTest.php resources/boost/skills/storefront-catalog/SKILL.md
git commit -m "feat: add storefront-catalog boost skill with resource validation test"
```

---

### Task 2: storefront-pages skill

**Files:**
- Create: `resources/boost/skills/storefront-pages/SKILL.md`
- Modify: `tests/Unit/BoostResourcesTest.php` (append to `$boostSkills`)

**Interfaces:**
- Consumes: `$boostSkills` dataset array in `tests/Unit/BoostResourcesTest.php` (from Task 1).

- [ ] **Step 1: Extend the test dataset**

In `tests/Unit/BoostResourcesTest.php`, change:

```php
$boostSkills = [
    'storefront-catalog',
];
```

to:

```php
$boostSkills = [
    'storefront-catalog',
    'storefront-pages',
];
```

- [ ] **Step 2: Run test to verify the new case fails**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: FAIL — `with ('storefront-pages')` case fails on `file_exists`; the catalog case still passes.

- [ ] **Step 3: Create the skill file**

Create `resources/boost/skills/storefront-pages/SKILL.md` with exactly this content:

````markdown
---
name: storefront-pages
description: Register Lunar Storefront routes, compose Inertia page props with the Props registry, and expose storefront Data DTOs as TypeScript types. Use when adding storefront pages or routes, sharing per-page props, or wiring spatie/laravel-data DTOs to the frontend in an app using lunarstorefront/storefront.
---

# Storefront Pages, Routes & Props

## When to use this skill

Use when wiring the `lunarstorefront/storefront` package into a consumer app's routing, Inertia pages, or TypeScript types.

## Route registration (opt-in)

The package's service provider registers NO routes. The host app opts in:

```php
use Lunar\Storefront\RouteRegistrar;

RouteRegistrar::register();                     // all groups
RouteRegistrar::register(['cart', 'currency']); // a subset
```

Groups: `cart`, `account`, `checkout`, `currency`, `suggestions`, `auth`. Unknown keys are silently ignored.

| Group | Routes (name — verb URI) |
|---|---|
| cart | `lunar.storefront.cart.lines` POST `cart/lines`; `lunar.storefront.cart.lines.quantity` PUT `cart/lines/{id}`; `lunar.storefront.cart.lines.delete` DELETE `cart/lines`; `lunar.storefront.cart.discount` POST `carts/discount`; `lunar.storefront.cart.discount.delete` DELETE `carts/discount` |
| account | `storefront.account.addresses` POST `/account/addresses`; `storefront.account.address` PUT `/account/addresses/{id}` (auth) |
| checkout | `checkout.success` GET `checkout/success`; `checkout.order-issue` GET `checkout/order-issue` |
| currency | `storefront.currency` POST `/api/currency` |
| suggestions | `storefront.query-suggestions` GET `api/query-suggestions` |
| auth | `auth.codes` GET `/api/auth/codes` (auth) |

`routes/catalog.php` is a separate opt-in file with one route — `storefront.api.products-hash` POST `products/variants/hash`, which turns an `options` array into an encrypted variant hash (`{"hash": "..."}`):

```php
// routes/web.php
require base_path('vendor/lunarstorefront/storefront/routes/catalog.php');
```

## Page prop composition

Three pieces: `StorefrontPage` (enum of page identifiers), `PropData` (a registration record), and the `Props` facade (the registry — a singleton, so registrations persist app-wide).

Register props in a service provider's `boot()`:

```php
use Lunar\Storefront\Facades\Props;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;

Props::add(new PropData(
    page: StorefrontPage::ProductsShow,
    key: 'reviews',
    callback: fn ($record) => Reviews::for($record), // $record = model passed to resolve()
));

// Arrays / collections of PropData also work; the callback may be an invokable class-string:
Props::add(new PropData(StorefrontPage::Global, 'menu', MenuProps::class));
```

Resolve in a controller and spread into the Inertia render:

```php
return Inertia::render('Products/Show', [
    'product' => \Lunar\Storefront\Data\Product::from($product),
    ...Props::resolve(StorefrontPage::ProductsShow, $product),
]);
```

`StorefrontPage` cases: `Global`, `ProductsShow`, `CollectionsShow`, `BrandsIndex`, `BrandsShow`, `CartIndex`, `SearchIndex`, `AccountIndex`, `AccountOrdersIndex`, `AccountAddressesIndex`, `AccountProfileIndex`, `CheckoutIndex`. The enum and its string value (`'products.show'`) are interchangeable in both `add()` and `resolve()`.

## Data DTO conventions

All DTOs live in `Lunar\Storefront\Data`, extend `Spatie\LaravelData\Data`, and carry the `#[TypeScript]` attribute.

- Build from models with static factories: `Product::fromModel($product)` (or `Product::from($product)`).
- Relations are `Lazy::whenLoaded(...)` — eager load the relation or it won't serialize into props.
- Money is integer minor units, with formatted string variants alongside (`subTotal` + `subTotalFormatted`).

```php
use Lunar\Storefront\Data\Cart;

// Cart with lines serialized:
Cart::fromModel($cart->load('lines'));
```

## TypeScript types

Nothing self-registers. Point `spatie/laravel-typescript-transformer` at the package's Data directory via the autoloaded `storefront_data_path()` helper:

```php
// config/typescript-transformer.php
'auto_discover_types' => [
    app_path(),
    storefront_data_path(),
],
```

Then run `php artisan typescript:transform` to emit the types.

## Config

`config/storefront.php` has a single option, `key` (from the `STOREFRONT_KEY` env). It is currently unused by package code — variant option hashing uses the app encryption key (`APP_KEY`).
````

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: PASS — 1 test, 2 dataset cases.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/BoostResourcesTest.php resources/boost/skills/storefront-pages/SKILL.md
git commit -m "feat: add storefront-pages boost skill"
```

---

### Task 3: storefront-auth-account skill

**Files:**
- Create: `resources/boost/skills/storefront-auth-account/SKILL.md`
- Modify: `tests/Unit/BoostResourcesTest.php` (append to `$boostSkills`)

**Interfaces:**
- Consumes: `$boostSkills` dataset array in `tests/Unit/BoostResourcesTest.php` (from Task 1).

- [ ] **Step 1: Extend the test dataset**

Append `'storefront-auth-account',` to `$boostSkills` in `tests/Unit/BoostResourcesTest.php`:

```php
$boostSkills = [
    'storefront-catalog',
    'storefront-pages',
    'storefront-auth-account',
];
```

- [ ] **Step 2: Run test to verify the new case fails**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: FAIL — only the `with ('storefront-auth-account')` case fails.

- [ ] **Step 3: Create the skill file**

Create `resources/boost/skills/storefront-auth-account/SKILL.md` with exactly this content:

````markdown
---
name: storefront-auth-account
description: Build customer account and auth-adjacent features with Lunar Storefront - address book endpoints, two-factor status, customer/order/address DTOs, and account page prop composition. Use when creating login, registration, account, order history, or address book pages in an app using lunarstorefront/storefront.
---

# Storefront Auth & Account

## What the package provides vs the host app

This package ships a deliberately thin auth/account layer:

- Login, registration, logout, password reset, and 2FA enable/confirm/disable are NOT included — use Laravel Fortify (or a starter kit built on it) in the host app. The package's account routes just assume an authenticated user.
- The package provides: an address create/update endpoint, a 2FA status endpoint, account page identifiers for prop registration, and DTOs (`Customer`, `Address`, `Order`) for shaping account page props.

## Address book

Register the `account` route group:

```php
\Lunar\Storefront\RouteRegistrar::register(['account']);
```

- `POST /account/addresses` (name `storefront.account.addresses`) — create
- `PUT /account/addresses/{id}` (name `storefront.account.address`) — update

Both run `auth` + `web` middleware, write to `$request->user()->latestCustomer()->addresses()`, and return `back()` — post to them with Inertia forms and reload props.

Fields: `first_name` (required), `city` (required), `state` (required), `postcode` (required), `country_id` (required), `last_name`, `company_name`, `line_one`, `line_two`, plus unvalidated `contact_phone`, `contact_email`, `delivery_instructions`. Sending `type: 'billing'` flags the address as the billing default; any other value flags it as the shipping default.

There is no delete-address or profile-update endpoint — add those in the host app.

## Two-factor status

`GET /api/auth/codes` (name `auth.codes`, group `auth`, middleware `web` + `auth`) returns JSON for a 2FA settings UI:

```json
{
  "optIn": true,
  "enabled": true,
  "qrCode": "<svg ...>",
  "recoveryCodes": ["..."],
  "status": null
}
```

It relies on Fortify's 2FA columns and methods on the User model. Enabling, confirming, and disabling 2FA go through Fortify's own routes.

## Building account pages

No account controllers or Inertia pages ship with the package. Build host controllers and compose props with the Props registry and the account page identifiers:

```php
use Lunar\Storefront\Facades\Props;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;
use Lunar\Storefront\Data\Address;

// In a service provider's boot():
Props::add(new PropData(
    page: StorefrontPage::AccountAddressesIndex,
    key: 'addresses',
    callback: fn () => Address::collect(auth()->user()->latestCustomer()->addresses),
));

// In the host controller:
return Inertia::render('Account/Addresses', [
    ...Props::resolve(StorefrontPage::AccountAddressesIndex),
]);
```

Account page cases: `AccountIndex`, `AccountOrdersIndex`, `AccountAddressesIndex`, `AccountProfileIndex`.

## Order history

Use the `Order` DTO. Money fields are integer minor units; relations are `Lazy` and only serialize when eager-loaded:

```php
use Lunar\Storefront\Data\Order;

$orders = Order::collect(
    $user->latestCustomer()->orders()
        ->with(['billingAddress', 'shippingAddress', 'physicalLines', 'transactions'])
        ->latest('placed_at')
        ->get()
);
```

DTO shapes:

- `Customer`: firstName, lastName, companyName?, taxIdentifier?
- `Address`: id, title, firstName, lastName, companyName, lineOne/Two/Three, city, state, postcode, countryId, contactEmail, contactPhone, countryIso, countryName (all nullable strings)
- `Order`: id, status, reference, integer totals (subTotal, discountTotal, shippingTotal, taxTotal, total), currencyCode, placedAt, plus Lazy billingAddress, shippingAddress, transactions, physicalLines
````

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: PASS — 1 test, 3 dataset cases.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/BoostResourcesTest.php resources/boost/skills/storefront-auth-account/SKILL.md
git commit -m "feat: add storefront-auth-account boost skill"
```

---

### Task 4: storefront-ui-vue skill

**Files:**
- Create: `resources/boost/skills/storefront-ui-vue/SKILL.md`
- Modify: `tests/Unit/BoostResourcesTest.php` (append to `$boostSkills`)

**Interfaces:**
- Consumes: `$boostSkills` dataset array in `tests/Unit/BoostResourcesTest.php` (from Task 1).

- [ ] **Step 1: Extend the test dataset**

Append `'storefront-ui-vue',` to `$boostSkills` in `tests/Unit/BoostResourcesTest.php`:

```php
$boostSkills = [
    'storefront-catalog',
    'storefront-pages',
    'storefront-auth-account',
    'storefront-ui-vue',
];
```

- [ ] **Step 2: Run test to verify the new case fails**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: FAIL — only the `with ('storefront-ui-vue')` case fails.

- [ ] **Step 3: Create the skill file**

Create `resources/boost/skills/storefront-ui-vue/SKILL.md` with exactly this content. Note: this skill deliberately omits the package's checkout/payment composables and components — that area is being reworked.

````markdown
---
name: storefront-ui-vue
description: Build Vue 3 storefront frontends with @lunarstorefront/vue3 - cart, product option selection, price display, search facets, addresses, and currency components and composables consuming Lunar Storefront Inertia props. Use when writing Vue pages or components in an app using lunarstorefront/storefront together with the @lunarstorefront/vue3 npm package.
---

# Storefront UI (Vue 3)

## When to use this skill

Use when building Vue pages or components with `@lunarstorefront/vue3` on top of the `lunarstorefront/storefront` Inertia backend.

## Setup expectations

- The package ships raw TypeScript and `.vue` source (no prebuilt dist) — the host app needs Vite with `@vitejs/plugin-vue`.
- `vue` and `@inertiajs/vue3` are direct dependencies of the package; make sure your bundler dedupes them so a single Vue instance is used.
- TypeScript types are hand-maintained mirrors of the server DTOs — import them from `@lunarstorefront/vue3` (or the `@lunarstorefront/vue3/types` subpath).

Each frontend feature expects its server route group to be registered via `RouteRegistrar::register([...])`:

| Frontend feature | Server route group |
|---|---|
| `AddToCart`, `useCart` discounts | `cart` |
| `CurrencySwitcher` | `currency` |
| `SearchInput` suggestions | `suggestions` |
| `useAccount` addresses | `account` |
| `TwoFactorAuthentication` | `auth` |

## Composables

```ts
import {
  useStorefront, useCart, useProduct,
  provideSearch, useSearchContext, useAccount,
} from '@lunarstorefront/vue3'
```

- `useStorefront()` — `pricesInclTax` ref (cookie-backed), `toggleTaxDisplay()`, `setTaxDisplay(incl)`, `formatPrice(price)`
- `useCart()` — `addToCartForm`, `applyDiscount(coupon)`, `clearDiscounts()`, `hasDiscount`, `discountError`; reads `page.props.cart`
- `useProduct()` — `variantExists(sel, optionId, valueId)`, `variantPurchasable(sel, optionId, valueId)`, `getMatchingPermutation(sel)`; reads `page.props.permutations`
- `useAccount()` — `addressForm`, `createAddress()`, `updateAddress(id)`; posts to the `account` route group
- Search — call `provideSearch()` once on the listing page; call `useSearchContext()` in descendants to share its state

## Product page pattern

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useProduct, AddToCart } from '@lunarstorefront/vue3'
import type { SelectionMap } from '@lunarstorefront/vue3'

defineProps<{ sku: string }>()

const { variantPurchasable, getMatchingPermutation } = useProduct()
const selected = ref<SelectionMap>({}) // { [optionId]: valueId }
const qty = ref(1)

// Disable an option value when no purchasable variant matches the current selection:
const canPick = (optionId: string, valueId: string) =>
  variantPurchasable(selected.value, optionId, valueId)
</script>

<template>
  <AddToCart v-model="qty" :sku="sku" /> <!-- POSTs to cart/lines -->
</template>
```

## Search listing pattern

```vue
<!-- Listing page: create the shared search instance once -->
<script setup lang="ts">
import { provideSearch } from '@lunarstorefront/vue3' // reads page.props.results
provideSearch()
</script>
```

```vue
<!-- Any descendant component -->
<script setup lang="ts">
import { useSearchContext } from '@lunarstorefront/vue3'
const { results, toggleFacet, setSort, clearFacets } = useSearchContext()
// mutations re-query via router.reload({ only: ['results'] })
</script>
```

Or use the packaged components: `SearchResults` (calls `provideSearch()` internally and exposes the whole API through its slot), `SearchInput` (v-model + debounced suggestions), `SearchHits`, `SearchFacets`, `SearchSortBy`, `SearchPagination`, `CollectionHierarchy`.

## Components

Styled components expose a default slot with scoped bindings, so markup is fully overridable.

- `AddToCart` — v-model quantity + `sku` prop
- `PriceDisplay` — `input` prop (a price map); honors the tax display toggle and the user's customer groups
- `PriceBreaks` — `currentQuantity` + `priceBreaks` props
- `TaxDisplayToggle`, `CurrencySwitcher`
- Forms: `FormInput`, `FormSelect`, `FormButton`, `FormControl`, `FormLabel`
- `TwoFactorAuthentication`, `TwoFactorCodeInput`

## Gotchas

- `SearchPerPage` uses its own state rather than the shared search context — prefer `useSearchContext().perPage` with `refreshResults()`.
- The `page.props` contract is `StorefrontPageProps` (cart, currencies, permutations, customerGroups, ...) — the server page must eager-load and pass the matching props, or composables see `undefined`.
````

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: PASS — 1 test, 4 dataset cases.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/BoostResourcesTest.php resources/boost/skills/storefront-ui-vue/SKILL.md
git commit -m "feat: add storefront-ui-vue boost skill"
```

---

### Task 5: Core guideline + guard tests

**Files:**
- Create: `resources/boost/guidelines/core.blade.php`
- Modify: `tests/Unit/BoostResourcesTest.php` (add two tests at the end of the file)

**Interfaces:**
- Consumes: `resources/boost/` tree from Tasks 1-4.

- [ ] **Step 1: Add guard tests**

Append to `tests/Unit/BoostResourcesTest.php`:

```php
it('ships the boost core guideline', function () {
    $file = dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php';

    expect(file_exists($file))->toBeTrue()
        ->and(trim(file_get_contents($file)))->not->toBeEmpty();
});

it('does not mention deferred integrations in boost resources', function () {
    $files = array_merge(
        glob(dirname(__DIR__, 2).'/resources/boost/skills/*/SKILL.md'),
        glob(dirname(__DIR__, 2).'/resources/boost/guidelines/*.blade.php'),
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(stripos(file_get_contents($file), 'stripe'))->toBeFalse();
    }
});
```

- [ ] **Step 2: Run tests to verify the guideline test fails**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: FAIL — `ships the boost core guideline` fails on `file_exists`; the no-mention test passes (skills are already clean); the 4 skill cases pass.

- [ ] **Step 3: Create the guideline**

Create `resources/boost/guidelines/core.blade.php` with exactly this content:

```blade
## Lunar Storefront

This package provides a headless, Inertia-based storefront layer for Lunar e-commerce: domain managers behind a `Storefront` facade, a per-page prop registry, spatie/laravel-data DTOs with TypeScript output, and opt-in route groups.

### Key conventions

- Catalog access goes through `Lunar\Storefront\Facades\Storefront`: `products()`, `collections()`, `brands()`, `variants()`, `search()`, `pricing()`.
- Data crossing to the frontend uses `Lunar\Storefront\Data\*` DTOs. Relations are Lazy — eager load them or they won't serialize. Money is integer minor units with formatted string variants alongside.
- Routes are opt-in: the host app calls `Lunar\Storefront\RouteRegistrar::register([...])` (groups: cart, account, checkout, currency, suggestions, auth).
- Per-page Inertia props are registered with `Props::add(new PropData(page, key, callback))` and resolved with `Props::resolve($page, $record)` using the `StorefrontPage` enum.

@verbatim
<code-snippet name="Fetch a product and render with resolved props" lang="php">
$product = Storefront::products()->getModelBySlug($slug);

return Inertia::render('Products/Show', [
    'product' => \Lunar\Storefront\Data\Product::from($product),
    ...Props::resolve(StorefrontPage::ProductsShow, $product),
]);
</code-snippet>
@endverbatim

### Skills

Activate the storefront skills for detailed work: `storefront-catalog` (products, collections, brands, search, pricing), `storefront-pages` (routes, props, TypeScript types), `storefront-auth-account` (accounts, addresses, 2FA), `storefront-ui-vue` (the @lunarstorefront/vue3 frontend package).
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/BoostResourcesTest.php`
Expected: PASS — 3 tests, 6 total cases.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/BoostResourcesTest.php resources/boost/guidelines/core.blade.php
git commit -m "feat: add boost core guideline and resource guard tests"
```

---

### Task 6: Bump laravel/boost and finish

**Files:**
- Modify: `composer.json` (require-dev `laravel/boost`)

**Interfaces:**
- Consumes: nothing from prior tasks; independent dependency bump.

- [ ] **Step 1: Bump the constraint**

In `composer.json`, change:

```json
"laravel/boost": "^1.8",
```

to:

```json
"laravel/boost": "^2.0",
```

- [ ] **Step 2: Update the lock file**

Run: `composer update laravel/boost --with-all-dependencies`
Expected: `laravel/boost` upgrades to a 2.x release. If the resolver reports conflicts, stop and report them rather than forcing.

- [ ] **Step 3: Run the full suite**

Run: `composer test`
Expected: PASS — all existing tests plus the 3 Boost resource tests.

Run: `vendor/bin/pint --test`
Expected: PASS (only `tests/Unit/BoostResourcesTest.php` is new PHP; fix with `vendor/bin/pint` if it flags styling).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: bump laravel/boost to ^2.0 for skills support"
```
