# Zones and Rates Reference

## ShippingZone Model — `Lunar\TableRateShipping\Models\ShippingZone`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| name | string | Display name |
| type | string | unrestricted, countries, states, postcodes |

### Zone Types

- **unrestricted** — matches all addresses (fallback zone)
- **countries** — matches by attached country list
- **states** — matches by attached state list
- **postcodes** — matches by postcode patterns AND attached countries

### Relationships

```php
$zone->countries();             // BelongsToMany Country
$zone->states();                // BelongsToMany State
$zone->postcodes();             // HasMany ShippingZonePostcode
$zone->rates();                 // HasMany ShippingRate
$zone->shippingExclusionLists(); // BelongsToMany ShippingExclusionList
```

### Postcode Patterns

```php
$zone->postcodes()->create(['postcode' => '90210']);   // Exact match
$zone->postcodes()->create(['postcode' => '90*']);     // Starts with 90
$zone->postcodes()->create(['postcode' => '9021?']);   // Single char wildcard
```
Postcodes are normalized automatically (uppercase, trimmed).

## ZoneResolver — `Lunar\TableRateShipping\Resolvers\ZoneResolver`

### Fluent API

```php
$resolver = new ZoneResolver();

$zones = $resolver
    ->country($country)      // Country model
    ->state('CA')            // State code string
    ->postcode('90210')      // Postcode string
    ->get();                 // Collection of matching ShippingZone
```

Resolution priority: postcode zones > state zones > country zones > unrestricted zones

## ShippingMethod Model — `Lunar\TableRateShipping\Models\ShippingMethod`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| name | string | Display name |
| description | string | Nullable |
| code | string | Unique identifier |
| driver | string | Driver key (flat-rate, free-shipping, ship-by, collection, or custom) |
| enabled | boolean | Active flag |
| cutoff | time | Nullable, daily cutoff for same-day dispatch |
| stock_available | boolean | Only show when all items in stock |
| data | json | Driver-specific configuration |

### Filtering Logic

Before a driver is invoked, methods are filtered:
1. `enabled` must be `true`
2. If `cutoff` set, current time must be before cutoff
3. If `stock_available` is `true`, all cart items must be in stock

## ShippingRate Model — `Lunar\TableRateShipping\Models\ShippingRate`

### Traits

`HasPrices`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| shipping_method_id | foreignId | Required |
| shipping_zone_id | foreignId | Required |
| enabled | boolean | Active flag |

### Relationships

```php
$rate->shippingMethod();  // BelongsTo ShippingMethod
$rate->shippingZone();    // BelongsTo ShippingZone
$rate->prices();          // MorphMany Price (via HasPrices)
$rate->basePrices();      // MorphMany (min_quantity=1)
```

### Multi-Currency Pricing

```php
$rate->prices()->createMany([
    ['currency_id' => $usd->id, 'price' => 999, 'min_quantity' => 1],
    ['currency_id' => $gbp->id, 'price' => 799, 'min_quantity' => 1],
]);
```

### Tiered Pricing (with ship-by driver)

```php
// Weight tiers (min_quantity = weight in grams)
$rate->prices()->createMany([
    ['currency_id' => $usd->id, 'price' => 499, 'min_quantity' => 1],     // 0-999g
    ['currency_id' => $usd->id, 'price' => 899, 'min_quantity' => 1000],  // 1000-4999g
    ['currency_id' => $usd->id, 'price' => 1499, 'min_quantity' => 5000], // 5000g+
]);
```

## ShippingExclusionList Model — `Lunar\TableRateShipping\Models\ShippingExclusionList`

### Relationships

```php
$list->exclusions();       // HasMany ShippingExclusion
$list->shippingZones();    // BelongsToMany ShippingZone
```

## ShippingExclusion Model — `Lunar\TableRateShipping\Models\ShippingExclusion`

### Fields

- `shipping_exclusion_list_id` (foreignId)
- `excludable_type` (string) — morph type
- `excludable_id` (bigint) — morph ID

### Relationships

```php
$exclusion->exclusionList();  // BelongsTo ShippingExclusionList
$exclusion->excludable();     // MorphTo (Product, etc.)
```

## Order Integration

### Automatic Zone Recording

The `RecordOrderShippingZone` listener fires on `OrderCreated`:
```php
// Resolves zones from order's shipping address
// Syncs to order_shipping_zone pivot table
$order->shippingZones;  // Collection of ShippingZone
```

### ShippingOptionResolved Event

```php
use Lunar\TableRateShipping\Events\ShippingOptionResolved;

// Properties
$event->cart;     // Cart model
$event->rate;     // ShippingRate model
$event->option;   // ShippingOption data object
```