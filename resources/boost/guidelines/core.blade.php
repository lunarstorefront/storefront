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
