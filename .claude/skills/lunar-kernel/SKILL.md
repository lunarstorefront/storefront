---
name: lunar-kernel
description: Work with the Lunar v2 kernel module including attributes, blueprints, taxation, channels, customer groups, URLs, storefront context, and core model traits.
license: MIT
metadata:
  author: Lunar
---

# Lunar Kernel Development

## Overview

The kernel is the foundation of Lunar v2. It provides shared infrastructure that all other modules depend on: attribute system for dynamic fields, blueprint system for attribute schemas, tax calculation with pluggable drivers, multi-channel/region support via storefront context, URL generation, and composable model traits.

## When to Activate

- Activate when working with Lunar's attribute system (`HasAttributeData`, `Attribute`, `BlueprintAttribute`).
- Activate when configuring or extending tax calculation (`TaxManager`, `Tax` facade, `TaxDriver` interface).
- Activate when working with channels, customer groups, or storefront context.
- Activate when working with URLs, tags, or media on Lunar models.
- Activate when creating or extending models that use kernel traits.

## Scope

- In scope: attributes, blueprints, tax drivers, channels, customer groups, URLs, storefront context, currencies, regions, countries, tags, vendors, core model traits.
- Out of scope: products/catalog (use `lunar-catalog`), carts/orders (use `lunar-cart`/`lunar-orders`), promotions (use `lunar-promotions`).

## Workflow

1. Identify which kernel feature is relevant (attributes, tax, channels, etc.).
2. Read the appropriate reference in `references/`.
3. Use kernel traits on models and facades/managers for business logic.

## Core Concepts

### BaseModel and Table Prefix

All Lunar models extend `Lunar\Kernel\Models\BaseModel`, which applies the configurable table prefix (default `lunar_`):

```php
use Lunar\Kernel\Models\BaseModel;

class MyModel extends BaseModel
{
    // Table will be lunar_my_models
}
```

### Attribute System

Models use the `HasAttributeData` trait to store dynamic attributes. Attributes are defined as `Attribute` models with field types (Text, Number, TranslatedText, Toggle, Dropdown, ListField):

```php
use Lunar\Kernel\Models\Concerns\HasAttributeData;

class Product extends BaseModel
{
    use HasAttributeData;

    protected $casts = [
        'attribute_data' => AsAttributeData::class,
    ];
}

// Access attribute values
$product->attr('description');
```

### Blueprint System

Blueprints define attribute schemas. A `Blueprint` has `BlueprintSection`s containing `BlueprintAttribute`s:

```php
use Lunar\Kernel\Models\Concerns\HasBlueprint;

class Product extends BaseModel
{
    use HasBlueprint;

    // $product->blueprint() returns the Blueprint
}

// Query blueprints for a model type
Blueprint::forModelType('product')->get();
```

### Channels and Scheduling

The `HasChannels` trait enables multi-channel support with availability scheduling:

```php
use Lunar\Kernel\Models\Concerns\HasChannels;

class Product extends BaseModel
{
    use HasChannels;
}

// Schedule availability
$product->scheduleChannel($channel, starts: now(), ends: now()->addMonth());

// Query by channel
Product::channel($channel)->get();
```

### Customer Groups

The `HasCustomerGroups` trait controls visibility/pricing per customer segment:

```php
use Lunar\Kernel\Models\Concerns\HasCustomerGroups;

class Product extends BaseModel
{
    use HasCustomerGroups;
}

Product::query()->hasCustomerGroupAccess($group)->get();
```

### Tax System

Tax calculation uses a driver-based `TaxManager` accessed via the `Tax` facade:

```php
use Lunar\Kernel\Facades\Tax;

// Set addresses for tax resolution
Tax::setShippingAddress(new TaxAddress(
    countryId: $country->id,
    state: 'CA',
    postcode: '90210',
));

// Get tax breakdown
$breakdown = Tax::getBreakdown();

// Custom tax driver
class MyTaxDriver implements TaxDriver
{
    public function setPurchasable(Purchasable $purchasable): self { /* ... */ }
    public function setTaxClass(TaxClass $taxClass): self { /* ... */ }
    public function setCurrencyCode(string $code): self { /* ... */ }
    public function getBreakdown(int $subTotal): TaxBreakdown { /* ... */ }
}
```

### Storefront Context

The `StorefrontContext` facade manages the current channel, currency, region, and customer context:

```php
use Lunar\Kernel\Facades\StorefrontContext;

// Set region (cascades to channel, currency, language, tax zone)
StorefrontContext::setRegion($region);

// Read context
$channel = StorefrontContext::getChannel();
$currency = StorefrontContext::getCurrency();
$language = StorefrontContext::getLanguage();
$taxZone = StorefrontContext::getTaxZone();

// Customer context
StorefrontContext::setCustomer($customer);
StorefrontContext::setCustomerGroups($groups);
```

Drivers: `session` (default for web), `array` (for testing/API). Configured in `config/kernel.php` under `storefront_context`.

### URLs

The `HasUrls` trait provides SEO-friendly URL management:

```php
use Lunar\Kernel\Models\Concerns\HasUrls;

class Product extends BaseModel
{
    use HasUrls;
}

$product->urls();           // All URLs
$product->defaultUrl();     // Default URL
$product->localeUrl('en');  // Language-specific URL

// Query by URL
Product::byUrl('blue-widget', $language)->first();
```

## Do and Don't

Do:
- Use `HasAttributeData` with `AsAttributeData` cast for dynamic fields.
- Use the `Tax` facade and `TaxManager` for tax calculations — never hard-code tax logic.
- Use `StorefrontContext` to resolve channel/currency/region — don't hard-code these values.
- Use `HasChannels` and `HasCustomerGroups` traits for multi-channel/group support.
- Register custom field types via `FieldTypeManifest`.
- Use `Blueprint::forModelType()` scope to query blueprints by model type.

Don't:
- Don't create model contracts or use dependency injection for models — Lunar v2 uses models directly.
- Don't bypass the `lunar_` table prefix — use `BaseModel` or configure the prefix in `config/kernel.php`.
- Don't hard-code tax rates or zones — use the tax driver system.
- Don't store attribute data in regular columns — use the `attribute_data` JSON column with `HasAttributeData`.
- Don't access currency/channel directly from config — use `StorefrontContext` to resolve them.

## References

- `references/attributes-and-blueprints.md`
- `references/taxation.md`
- `references/storefront-context.md`