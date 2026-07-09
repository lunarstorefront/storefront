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
