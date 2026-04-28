# Pricing Reference

## Price Model — `Lunar\Catalog\Models\Price`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| priceable_type | string | Morph type (e.g., ProductVariant) |
| priceable_id | bigint | Morph ID |
| currency_id | foreignId | Required |
| customer_group_id | foreignId | Nullable (null = all groups) |
| price | integer | Amount in smallest currency unit |
| compare_price | integer | Nullable, original/RRP price |
| min_quantity | integer | Default: 1, for price breaks |

### Relationships

```php
$price->priceable();      // MorphTo (ProductVariant, etc.)
$price->currency();       // BelongsTo Currency
$price->customerGroup();  // BelongsTo CustomerGroup
```

### HasPrices Trait

```php
$variant->prices();       // MorphMany Price
$variant->basePrices();   // MorphMany (min_quantity=1, no customer group)
```

## PricingManager — `Lunar\Catalog\Managers\PricingManager`

### Facade: `Lunar\Catalog\Facades\Pricing`

### Fluent API

```php
Pricing::for(Purchasable $purchasable): static
Pricing::currency(Currency $currency): static
Pricing::qty(int $quantity): static
Pricing::customerGroups(Collection $groups): static
Pricing::region(Region $region): static   // For tax-aware pricing
Pricing::get(): PricingResponse
Pricing::getWithTax(): TaxAwarePricingResponse
```

### Pipeline Extension

```php
Pricing::addPipeline(MyPricingPipeline::class);
Pricing::getPipelines(): array;
```

### PricingResponse

```php
$response->matched;             // ?Price — best matching price
$response->base;                // ?Price — base price (qty=1, no group)
$response->priceBreaks;         // Collection of Price models
$response->customerGroupPrices; // Collection of group-specific prices
```

### TaxAwarePricingResponse

```php
$response->matched;   // ?TaxAwarePrice
$response->base;      // ?TaxAwarePrice

// TaxAwarePrice properties
$taxPrice->priceExcTax;    // int
$taxPrice->priceIncTax;    // int
$taxPrice->taxAmount;      // int
$taxPrice->breakdown;      // TaxBreakdown
$taxPrice->comparePrices;  // array (priceExcTax, priceIncTax for compare_price)
```

### FormatsPrices Trait

Available on Price, Cart, CartLine, Order, OrderLine:
```php
$price->format();    // Locale-formatted string (e.g., "$10.00")
$price->decimal();   // Float conversion (e.g., 10.00)
```

## Price Resolution Logic

1. Filter prices by currency
2. Filter by customer groups (include null group prices)
3. Find best match for requested quantity (highest min_quantity ≤ requested)
4. Run through pricing pipelines
5. Return matched price with alternatives