# Storefront Context Reference

## Facade: `Lunar\Kernel\Facades\StorefrontContext`

The `StorefrontContextManager` manages the current region, channel, currency, language, tax zone, customer, and customer groups for the storefront.

## Configuration

In `config/kernel.php`:
```php
'storefront_context' => [
    'driver' => env('LUNAR_STOREFRONT_CONTEXT_DRIVER', 'session'),
],
```

### Drivers

- `session` — persists context in session (default for web apps)
- `array` — in-memory, resets per request (for testing and API usage)

## Methods

### Region Management

```php
StorefrontContext::setRegion(Region $region): void
StorefrontContext::getRegion(): ?Region
```

Setting a region cascades to set the associated channel, currency, language, and tax zone.

### Read Context

```php
StorefrontContext::getChannel(): ?Channel
StorefrontContext::getCurrency(): ?Currency
StorefrontContext::getLanguage(): ?Language
StorefrontContext::getTaxZone(): ?TaxZone
```

### Customer Context

```php
StorefrontContext::setCustomer(Customer $customer): void
StorefrontContext::getCustomer(): ?Customer
StorefrontContext::setCustomerGroups(Collection $groups): void
StorefrontContext::getCustomerGroups(): Collection
```

### Clear Context

```php
StorefrontContext::forget(): void
```

## Events

- `StorefrontRegionChanged` — fired when region is set
- `StorefrontCustomerChanged` — fired when customer is set

## Usage Pattern

```php
// In a middleware or controller
StorefrontContext::setRegion(
    Region::where('code', 'US')->first()
);

// In service/action code, resolve context
$currency = StorefrontContext::getCurrency();
$channel = StorefrontContext::getChannel();
```