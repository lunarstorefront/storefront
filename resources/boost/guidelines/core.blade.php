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

- `php artisan storefront:install --preset=uk` sets up a new store (migrations, UK reference data, admin user, search index).

### Where code goes

- Only package code receives patches; a store's own controllers and pages never update. Keep store code a thin layer: package calls, then store-specific props.
- Logic a second store would need (pricing, stock, cart rules, validation, search, shipping) belongs in a package. Client-specific code never goes in a package.
- Extend through `Props`, container-bound contracts in `Lunar\Storefront\Contracts`, or by leaving a route group out of `RouteRegistrar::register()`. Never copy a package class into the app or edit `vendor/`.
- After a composer update, force-publish package assets and run `php artisan typescript:transform`. Upgrade `lunarstorefront/storefront` and `storefront-ui` together.

### Skills

Activate the storefront skills for detailed work: `storefront-store-development` (where code belongs, extending and promoting logic), `storefront-upgrades` (taking package updates, reviewing bump PRs), `storefront-catalog` (products, collections, brands, search, pricing, navigation, stock), `storefront-pages` (routes, props, TypeScript types, order confirmation, install), `storefront-auth-account` (accounts, addresses, 2FA), `storefront-ui-vue` (the @lunarstorefront/vue3 frontend package).
