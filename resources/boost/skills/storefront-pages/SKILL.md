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
