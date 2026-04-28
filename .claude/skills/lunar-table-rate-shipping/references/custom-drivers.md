# Custom Shipping Drivers Reference

## ShippingDriverInterface — `Lunar\TableRateShipping\Contracts\ShippingDriverInterface`

```php
interface ShippingDriverInterface
{
    public function resolve(ShippingOptionRequest $request): ?ShippingOption;
}
```

### ShippingOptionRequest — `Lunar\TableRateShipping\DataObjects\ShippingOptionRequest`

```php
// Properties
$request->shippingRate;  // ShippingRate model (includes method, zone, prices)
$request->cart;          // Cart model (includes lines, addresses, totals)
```

### ShippingOption — `Lunar\Sales\DataObjects\ShippingOption`

```php
new ShippingOption(
    name: string,           // Display name
    description: string,    // Description
    identifier: string,     // Unique identifier (method code)
    price: int,             // Price in smallest currency unit
    taxClass: ?TaxClass,    // Tax class for tax calculation
    collect: bool = false,  // Click-and-collect flag
);
```

## Implementation Example

```php
use Lunar\TableRateShipping\Contracts\ShippingDriverInterface;
use Lunar\TableRateShipping\DataObjects\ShippingOptionRequest;
use Lunar\Sales\DataObjects\ShippingOption;

class DistanceBasedDriver implements ShippingDriverInterface
{
    public function resolve(ShippingOptionRequest $request): ?ShippingOption
    {
        $rate = $request->shippingRate;
        $cart = $request->cart;
        $method = $rate->shippingMethod;

        // Access driver config from method's data field
        $config = $method->data;
        $pricePerMile = $config['price_per_mile'] ?? 100;

        // Get shipping address
        $shippingAddress = $cart->shippingAddress;
        if (! $shippingAddress) {
            return null;
        }

        // Custom distance calculation
        $distance = $this->calculateDistance(
            $config['origin_postcode'] ?? '',
            $shippingAddress->postcode
        );

        $price = $distance * $pricePerMile;

        // Optionally use rate prices as a base
        $basePrice = $rate->basePrices
            ->where('currency_id', $cart->currency_id)
            ->first();

        if ($basePrice) {
            $price += $basePrice->price;
        }

        return new ShippingOption(
            name: $method->name,
            description: $method->description ?? '',
            identifier: $method->code,
            price: $price,
            taxClass: $method->taxClass,
        );
    }

    private function calculateDistance(string $origin, string $destination): int
    {
        // Implement distance lookup
        return 10;
    }
}
```

## Registration

### Via Facade

```php
use Lunar\TableRateShipping\Facades\TableRateShipping;

// In a service provider's boot method
TableRateShipping::extend('distance-based', function () {
    return new DistanceBasedDriver();
});
```

### Usage

```php
// Create a method using the custom driver
ShippingMethod::create([
    'name' => 'Distance-Based Shipping',
    'code' => 'distance-based',
    'driver' => 'distance-based',
    'enabled' => true,
    'data' => [
        'origin_postcode' => '10001',
        'price_per_mile' => 50,
    ],
]);
```

## Built-in Driver Reference

### FlatRate — `Lunar\TableRateShipping\Drivers\FlatRate`

- Uses rate's base price for the cart's currency
- No additional configuration needed
- Returns null if no matching price found

### FreeShipping — `Lunar\TableRateShipping\Drivers\FreeShipping`

- Returns zero price
- Config: `minimum_spend` (currency-keyed array, optional)
- Returns null if cart total below minimum spend

### ShipBy — `Lunar\TableRateShipping\Drivers\ShipBy`

- Tiered pricing by weight or cart total
- Config: `charge_by` ('weight' or 'cart_total')
- Uses `min_quantity` on prices as tier thresholds
- For weight: calculates total cart weight from variants
- For cart_total: uses cart sub_total

### Collection — `Lunar\TableRateShipping\Drivers\Collection`

- Zero-price click-and-collect option
- Sets `collect: true` on the ShippingOption
- No additional configuration needed

## Tips

- Store driver config in the method's `data` JSON field for admin editability.
- Leverage rate prices for base pricing — your driver can add surcharges on top.
- Return `null` from `resolve()` when shipping is not available for the given cart/zone.
- Listen to `ShippingOptionResolved` event for post-resolution logic.
- Use `$request->shippingRate->shippingZone` to access zone details in your driver.