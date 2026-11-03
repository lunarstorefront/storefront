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
| checkout (legacy, see below) | `checkout.success` GET `checkout/success`; `checkout.order-issue` GET `checkout/order-issue` |
| currency | `storefront.currency` POST `/api/currency` |
| suggestions | `storefront.query-suggestions` GET `api/query-suggestions` |
| auth | `auth.codes` GET `/api/auth/codes` (auth) |

The `checkout` group is legacy. Its routes read `session('checkout.order')`, which current `lunarphp/checkout` no longer sets, so they cannot find the order. Leave the group out and own your confirmation route; use `GetCompletedOrder` (below).

`routes/catalog.php` is a separate opt-in file with one route — `storefront.api.products-hash` POST `products/variants/hash`, which turns an `options` array into a signed variant hash (`{"hash": "..."}`):

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

## Order confirmation

`lunarphp/checkout` hands the placed order over in `session('lunar.checkout.completed')`. Two actions read it. The order id never comes from the request, because the session is the only proof this browser placed the order.

```php
use Lunar\Storefront\Actions\Checkout\GetCompletedOrder;
use Lunar\Storefront\Actions\Checkout\GetOrderContactEmail;

public function handle(Session $session, array $with = []): ?Order;      // GetCompletedOrder
public function handle(Order $order, Session $session): ?string;         // GetOrderContactEmail

Route::get('order/confirmation', function (Request $request) {
    $order = (new GetCompletedOrder)->handle($request->session(), with: ['physicalLines', 'shippingLines', 'billingAddress', 'shippingAddress']);

    abort_if($order === null, 404); // nothing in the session, or the order is not placed

    return Inertia::render('Checkout/Confirmation', [
        'order' => \Lunar\Storefront\Data\Order::from($order),
        'email' => (new GetOrderContactEmail)->handle($order, $request->session()),
    ]);
});
```

The `Order` DTO's relations (`physicalLines`, `shippingLines`, `billingAddress`, `shippingAddress`, `transactions`) are Lazy and only serialize when loaded, hence the eager loads above.

`GetCompletedOrder` returns null unless the handover holds a numeric `order_reference` and that order has `placed_at` set. `GetOrderContactEmail` tries the billing then shipping address `contact_email`, then the email on the checkout session (a guest's email can live only there), then the order user's email; null when none exist.

If your confirmation route is not named `checkout.success`, add it to `storefront.buy_now.checkout_routes` (default `['lunar.checkout.*', 'checkout.success', 'checkout.order-issue']`), or an in-progress Buy Now restores the basket before the page forgets the cart.

## Installing a store

`php artisan storefront:install` sets up a new store. It stops with an error if a required package is missing, otherwise it runs in order:

1. Publish config (only if missing) and assets (always `--force`) from `storefront.install.publish`.
2. `migrate --force`.
3. Import countries (`lunar:import:address-data`) if there are none.
4. Seed the preset.
5. Create the admin user.
6. Build the search index.
7. `storage:link` if `public/storage` is missing.

Options: `--preset=uk` (default `uk`; unknown names are rejected, listing the available ones, before any changes are made), `--skip-search`, `--admin-email`, `--admin-firstname`, `--admin-lastname`, `--admin-password`. Each `--admin-*` option falls back to `config('storefront.install_admin.*')`, which reads `STOREFRONT_ADMIN_EMAIL`, `STOREFRONT_ADMIN_FIRSTNAME`, `STOREFRONT_ADMIN_LASTNAME` and `STOREFRONT_ADMIN_PASSWORD`. An existing admin email is left alone. Missing values are prompted for interactively; with `--no-interaction` the admin step is skipped with a warning. Safe to run more than once.

```bash
php artisan storefront:install --preset=uk --admin-email=me@example.com --admin-firstname=Ada --admin-lastname=Lovelace --admin-password=secret
php artisan storefront:install --skip-search --no-interaction
```

The `uk` preset seeds GBP, English, a Webstore channel, Retail and Trade customer groups, a Warehouse location, UK VAT tax zones and classes (Standard 20, Reduced 5, Zero 0) and UK shipping zones, all idempotently.

Add a preset by implementing `Lunar\Storefront\Install\Preset` (one method, `seed(): void`, safe to run twice) and registering it:

```php
// config/storefront.php (a published copy replaces whole top-level keys, so keep 'uk')
'presets' => [
    'uk' => \Lunar\Storefront\Install\Presets\UkPreset::class,
    'eu' => App\Install\EuPreset::class,
],
```

Config (`storefront.install`):

- `requires`: package name => a class that exists when installed. Defaults: `lunarphp/table-rate-shipping`, `lunarphp/panel`, `lunarphp/checkout`, `lunarphp/lunar-cms`, `neoncommerce/storefront-cache`.
- `publish.config`: `['panel-config']`
- `publish.assets`: `['panel-all-assets', 'lunar-checkout-assets']`
- `search_command`: `'lunar:search:index'`

## Config

`config/storefront.php` top-level keys: `key` (from `STOREFRONT_KEY`; unused by package code, variant option hashing uses `APP_KEY`), `buy_now.checkout_routes`, `install`, `install_admin`, `navigation`, `search`, `stock` and `presets`. The last six are documented in this skill and `storefront-catalog`. A store's published copy replaces whole top-level keys, so copy a key in full when overriding it.
