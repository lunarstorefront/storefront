# Taxation Reference

## Models

### TaxClass — `Lunar\Kernel\Models\TaxClass`

- Traits: `HasDefaultRecord`, `LogsActivity`
- Fields: name
- Example: "Standard Rate", "Reduced Rate", "Zero Rate"

### TaxZone — `Lunar\Kernel\Models\TaxZone`

- Fields: name, zone_type (country, state, postcode), active, default
- Relationships: `countries()`, `states()`, `postcodes()`, `taxRates()`
- Sub-models: `TaxZoneCountry`, `TaxZoneState`, `TaxZonePostcode`

### TaxRate — `Lunar\Kernel\Models\TaxRate`

- Fields: tax_zone_id, name, priority
- Relationships: `taxZone()`, `taxRateAmounts()`

### TaxRateAmount — `Lunar\Kernel\Models\TaxRateAmount`

- Fields: tax_rate_id, tax_class_id, percentage (decimal 7,3)
- Relationships: `taxRate()`, `taxClass()`

## Facade: `Lunar\Kernel\Facades\Tax`

### Methods

```php
Tax::setShippingAddress(TaxAddress $address): void
Tax::setBillingAddress(TaxAddress $address): void
Tax::setTaxZone(TaxZone $zone): void
Tax::getBreakdown(): TaxBreakdown
```

### TaxAddress Data Object

```php
new TaxAddress(
    countryId: int,
    state: ?string,
    postcode: ?string,
)
```

### TaxBreakdown Data Object

```php
$breakdown = new TaxBreakdown();
$breakdown->addAmount(TaxBreakdownAmount $amount);
$breakdown->getAmounts(): Collection;
$breakdown->total(): int;
```

### TaxBreakdownAmount Data Object

```php
new TaxBreakdownAmount(
    amount: int,
    identifier: string,
    description: string,
    percentage: float,
)
```

### PriceDisplay Enum

```php
Lunar\Kernel\DataObjects\PriceDisplay::ExcludeTax
Lunar\Kernel\DataObjects\PriceDisplay::IncludeTax
```

## Custom Tax Driver

Implement `Lunar\Kernel\Contracts\TaxDriver`:

```php
use Lunar\Kernel\Contracts\TaxDriver;
use Lunar\Kernel\Contracts\Purchasable;

class MyTaxDriver implements TaxDriver
{
    public function setPurchasable(Purchasable $purchasable): self { /* ... */ }
    public function setTaxClass(TaxClass $taxClass): self { /* ... */ }
    public function setCurrencyCode(string $code): self { /* ... */ }
    public function getBreakdown(int $subTotal): TaxBreakdown { /* ... */ }
}
```

Register in `config/kernel.php`:
```php
'taxation' => [
    'driver' => 'custom',
    'drivers' => [
        'custom' => MyTaxDriver::class,
    ],
],
```