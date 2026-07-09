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
