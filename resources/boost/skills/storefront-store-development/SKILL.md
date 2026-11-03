---
name: storefront-store-development
description: Decide where code belongs in a store built on lunarstorefront/storefront (the store app or a shared package), shape controllers as package calls plus store-specific props, extend the package without copying it, and promote shared logic into a package safely. Use when adding a feature, page, controller, listener or Vue component to a storefront app, or when the same logic is needed by more than one store.
---

# Building in a Storefront App

## When to use this skill

Use before writing feature code in a store that depends on `lunarstorefront/storefront` and `@lunarstorefront/vue3`. It decides where the code goes. The other storefront skills cover how to use each part.

## The rule that drives everything

Only code that lives in a package receives patches. A store's own controllers, pages and config are copied once and never update. So:

- Keep the store's code a thin layer over the packages.
- Business logic (pricing, stock, cart rules, validation, search, shipping zone matching) belongs in a package, where a fix reaches every store through `composer update` or `npm update`.

## Where code goes

| Layer | Owns | Updates via |
|---|---|---|
| `lunarphp/*` | Models, cart, pricing, orders, search, panel, shipping | composer |
| `lunarstorefront/storefront` | `Storefront` managers, actions, Data DTOs, `Props` registry, `StorefrontPage`, `RouteRegistrar` groups | composer |
| `@lunarstorefront/vue3` | Composables, generic components, shared TypeScript types | npm (git tag) |
| `lunarphp/checkout` | The checkout app, prebuilt | composer |
| The store app | Controllers, Inertia pages, layout, styling, config, client-only features | never |

Decide with one question: **would a second store need this?**

- **Yes:** it goes in a package. Generic PHP goes in `lunarstorefront/storefront`; generic Vue goes in `@lunarstorefront/vue3`.
- **No:** it stays in the store. Client integrations, client copy and branding, and one-off business rules are store code.
- **Never** put client-specific code, client names or client config keys in a shared package.

## Controller shape

A storefront controller is package calls, then store-specific props, then the registered page props.

```php
use Inertia\Inertia;
use Lunar\Storefront\Data\Product;
use Lunar\Storefront\Facades\Props;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\StorefrontPage;

public function __invoke(string $slug)
{
    $product = Storefront::products()->getModelBySlug($slug);
    $variant = $product->variants->sortBy('id')->first();
    $pricing = $variant ? Storefront::pricing()->getPricing($variant) : null;
    $options = Storefront::products()->getOptions($product);

    return Inertia::render('products/Show', [
        'product' => Product::from($product),
        'price' => $pricing ? Storefront::pricing()->getQuantifiedPrice($pricing, 1) : null,
        'priceBreaks' => $pricing ? Storefront::pricing()->mapPriceBreaks($pricing) : [],
        'options' => $options,
        'permutations' => Storefront::products()->getPermutations($product, $options),
        // store-specific props go here
        ...Props::resolve(StorefrontPage::ProductsShow, $product),
    ]);
}
```

Warning signs that logic is in the wrong place:

- A controller queries Lunar models directly for something a manager already does (slug lookup, pricing, options, breadcrumbs).
- A controller calculates prices, tax, stock or totals.
- Two stores have a file at the same path with nearly the same contents.

## Extending without copying

Never copy a package class into the app to change it, and never edit `vendor/`. A copy stops receiving fixes. Use these seams instead, most preferred first:

1. **Extra page data:** register a prop with `Props::add(new PropData(StorefrontPage::ProductsShow, 'reviews', ...))` in a service provider, or add a key in the store's own controller.
2. **Different behaviour for a manager:** the managers resolve through the container (`Storefront::products()` is `app(Contracts\ProductManager::class)`). Extend the package manager and rebind the contract in a service provider:

   ```php
   $this->app->bind(
       \Lunar\Storefront\Contracts\ProductManager::class,
       \App\Storefront\ProductManager::class, // extends Lunar\Storefront\Managers\ProductManager
   );
   ```

3. **A route the package registers that the store needs to own:** leave that group out of `RouteRegistrar::register([...])` and register the store's own route.
4. **A frontend component that almost fits:** wrap or compose it in the store. If the gap is generic, add an option to the package component instead.

## Frontend

- Pages compose `@lunarstorefront/vue3` composables and components (`useStorefront`, `useCart`, `useSearchContext`, `PriceDisplay`, `AddToCart`, `PriceBreaks`, ...). Load the `storefront-ui-vue` skill for the API.
- Styling, layout and markup are the store's. Restyle freely.
- If a page needs something generic the package lacks, such as a breadcrumb or applied-filters list, add it to `@lunarstorefront/vue3` rather than the store.

## Promoting logic into a package

When the answer to "would a second store need this?" changes to yes:

1. Build one generic version in the package. Do not move a store's copy across verbatim; strip client names, config keys and assumptions.
2. Keep the change additive. Existing public methods, route names and DTO shapes other stores depend on must not change.
3. **Anything that registers listeners, routes or middleware is opt-in**, through the package config, and defaults off. Some stores track package `dev-main` and pick up merged code on their next `composer update`. A listener that switches itself on can, for example, send a second order confirmation email in a store that already has its own.
4. Commands, actions, DTOs and data presets do nothing until called, so they need no flag.
5. Tag a release, then switch the store over and delete its local copy in the same change.
