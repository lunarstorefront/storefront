---
name: lunar-table-rate-shipping
description: Work with Lunar's table rate shipping module including shipping zones, methods, rates, built-in drivers, exclusion lists, custom driver development, and order integration.
license: MIT
metadata:
  author: Lunar
---

# Lunar Table Rate Shipping Development

## Overview

The table rate shipping module provides zone-based shipping calculation for Lunar. Shipping zones define geographic regions, methods define shipping options within zones, and rates link methods to zones with pricing. The module uses a driver pattern for shipping calculation — built-in drivers handle common scenarios, and custom drivers can be registered for specialized logic. It integrates with the cart pipeline via the `ShippingManifestInterface`.

## When to Activate

- Activate when configuring shipping zones, methods, or rates.
- Activate when implementing custom shipping drivers.
- Activate when working with shipping exclusions or zone resolution.
- Activate when code references `ShippingZone`, `ShippingMethod`, `ShippingRate`, `TableRateShipping`, or `TableRateShippingManifest`.

## Scope

- In scope: shipping zones, methods, rates, drivers, exclusion lists, zone resolution, order-zone tracking, custom driver development.
- Out of scope: cart pipeline mechanics (use `lunar-cart`), payment processing (use `lunar-orders`).

## Workflow

1. Create shipping zones for geographic regions.
2. Create shipping methods with a driver (flat-rate, free-shipping, ship-by, collection).
3. Create rates linking methods to zones with prices.
4. Cart calculation automatically resolves shipping options via the manifest.

## Core Concepts

### Zone → Method → Rate Structure

```
ShippingZone (geographic region)
  └── ShippingRate (links zone to method with pricing)
        └── ShippingMethod (driver-based calculation)
              └── Price (multi-currency via HasPrices)
```

### Shipping Zones

```php
use Lunar\TableRateShipping\Models\ShippingZone;

// Unrestricted zone (matches all addresses)
$zone = ShippingZone::create([
    'name' => 'Domestic',
    'type' => 'unrestricted',
]);

// Country-based zone
$zone = ShippingZone::create([
    'name' => 'US Shipping',
    'type' => 'countries',
]);
$zone->countries()->attach($usCountry);

// State-based zone
$zone = ShippingZone::create([
    'name' => 'California',
    'type' => 'states',
]);
$zone->states()->attach($californiaState);

// Postcode-based zone (must also have countries)
$zone = ShippingZone::create([
    'name' => 'Los Angeles Area',
    'type' => 'postcodes',
]);
$zone->countries()->attach($usCountry);
$zone->postcodes()->create(['postcode' => '90*']); // Wildcard support
```

### Zone Resolution

```php
use Lunar\TableRateShipping\Resolvers\ZoneResolver;

$zones = (new ZoneResolver)
    ->country($country)
    ->state('CA')
    ->postcode('90210')
    ->get();
```

### Shipping Methods

```php
use Lunar\TableRateShipping\Models\ShippingMethod;

$method = ShippingMethod::create([
    'name' => 'Standard Shipping',
    'code' => 'standard',
    'driver' => 'flat-rate',
    'enabled' => true,
    'cutoff' => '14:00',         // Optional: daily cutoff time
    'stock_available' => true,   // Only show if all items in stock
    'data' => [],                // Driver-specific config
]);
```

### Shipping Rates

```php
use Lunar\TableRateShipping\Models\ShippingRate;

$rate = ShippingRate::create([
    'shipping_method_id' => $method->id,
    'shipping_zone_id' => $zone->id,
    'enabled' => true,
]);

// Add prices (multi-currency via HasPrices trait)
$rate->prices()->create([
    'currency_id' => $usd->id,
    'price' => 999,         // $9.99
    'min_quantity' => 1,    // For tiered pricing
]);
```

### Built-in Drivers

#### FlatRate (`flat-rate`)

Fixed price per zone. Uses the rate's base prices.
```php
$method = ShippingMethod::create([
    'name' => 'Standard',
    'code' => 'standard',
    'driver' => 'flat-rate',
]);
```

#### FreeShipping (`free-shipping`)

Zero-price shipping with optional minimum spend threshold.
```php
$method = ShippingMethod::create([
    'name' => 'Free Shipping',
    'code' => 'free',
    'driver' => 'free-shipping',
    'data' => [
        'minimum_spend' => ['USD' => 5000],  // Optional: $50 minimum
    ],
]);
```

#### ShipBy (`ship-by`)

Tiered pricing by weight or cart total.
```php
$method = ShippingMethod::create([
    'name' => 'Weight-Based',
    'code' => 'weight-based',
    'driver' => 'ship-by',
    'data' => [
        'charge_by' => 'weight',  // or 'cart_total'
    ],
]);

// Use min_quantity on prices for tier thresholds
$rate->prices()->createMany([
    ['currency_id' => $usd->id, 'price' => 599, 'min_quantity' => 1],     // 0-4.99kg: $5.99
    ['currency_id' => $usd->id, 'price' => 999, 'min_quantity' => 500],   // 5kg+: $9.99
]);
```

#### Collection (`collection`)

Click-and-collect / in-store pickup. Zero price, sets `collect: true`.
```php
$method = ShippingMethod::create([
    'name' => 'Click & Collect',
    'code' => 'collect',
    'driver' => 'collection',
]);
```

### Custom Shipping Driver

```php
use Lunar\TableRateShipping\Contracts\ShippingDriverInterface;
use Lunar\TableRateShipping\DataObjects\ShippingOptionRequest;
use Lunar\Sales\DataObjects\ShippingOption;

class ExpressDriver implements ShippingDriverInterface
{
    public function resolve(ShippingOptionRequest $request): ?ShippingOption
    {
        $rate = $request->shippingRate;
        $cart = $request->cart;

        // Custom calculation logic
        $price = $this->calculatePrice($cart, $rate);

        if (! $price) {
            return null; // Not available
        }

        return new ShippingOption(
            name: $rate->shippingMethod->name,
            description: $rate->shippingMethod->description,
            identifier: $rate->shippingMethod->code,
            price: $price,
            taxClass: $rate->shippingMethod->taxClass,
            collect: false,
        );
    }
}

// Register in service provider
use Lunar\TableRateShipping\Facades\TableRateShipping;

TableRateShipping::extend('express', function () {
    return new ExpressDriver();
});
```

### Exclusion Lists

Exclude products from shipping zones:
```php
use Lunar\TableRateShipping\Models\ShippingExclusionList;

$list = ShippingExclusionList::create(['name' => 'Hazardous Materials']);

// Add exclusions (polymorphic)
$list->exclusions()->create([
    'excludable_type' => 'product',
    'excludable_id' => $product->id,
]);

// Attach to zones
$list->shippingZones()->attach($zone);
```

If all cart items are excluded from all zones, no shipping options are returned.

### Order Integration

The module listens to `OrderCreated` and records which shipping zones were used:
```php
// On Order model (dynamically registered)
$order->shippingZones;  // BelongsToMany ShippingZone

// Query orders by zone
Order::whereHas('shippingZones', fn ($q) => $q->where('id', $zone->id))->get();
```

### Events

- `ShippingOptionResolved` — fired during cart calculation with the resolved rate and option

### Configuration

In `config/table-rate-shipping.php`:
```php
return [
    'enabled' => true,
    'tax_calculation' => 'default',  // 'default' or 'highest'
];
```

Tax calculation modes:
- `default` — uses the rate's tax class
- `highest` — uses the highest tax class from cart items

## Do and Don't

Do:
- Use `TableRateShipping::extend()` to register custom drivers.
- Use zone types appropriately: unrestricted for fallback, countries/states/postcodes for specificity.
- Use `min_quantity` on rate prices for tier-based pricing with `ship-by` driver.
- Store driver-specific config in the method's `data` JSON field.
- Use exclusion lists to prevent shipping of restricted products.
- Set `cutoff` time on methods for same-day dispatch cutoffs.

Don't:
- Don't modify `ShippingManifestInterface` binding manually — the package handles it.
- Don't hard-code shipping prices — use rates with multi-currency prices.
- Don't create zones without setting the correct type and geographic associations.
- Don't use postcode zones without also attaching countries.
- Don't bypass zone resolution by querying rates directly — use `ZoneResolver`.

## References

- `references/zones-and-rates.md`
- `references/custom-drivers.md`