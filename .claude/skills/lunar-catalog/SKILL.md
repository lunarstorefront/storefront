---
name: lunar-catalog
description: Work with the Lunar v2 catalog module including products, variants, pricing, brands, collections, product options, product types, and associations.
license: MIT
metadata:
  author: Lunar
---

# Lunar Catalog Development

## Overview

The catalog module manages the product catalog: products with variants and pricing, organized into collections and brands. Products use a type system that links to attribute blueprints for flexible data modeling. Pricing supports multi-currency, customer group tiers, and price breaks through a pipeline-based manager.

## When to Activate

- Activate when working with products, variants, SKUs, or product types.
- Activate when implementing pricing logic, price breaks, or customer group pricing.
- Activate when managing collections (categories), brands, or product associations.
- Activate when code references `Product`, `ProductVariant`, `Price`, `Collection`, `Brand`, `PricingManager`, or the `Pricing` facade.

## Scope

- In scope: products, variants, pricing, collections, brands, product options, product types, product associations, product states.
- Out of scope: cart operations (use `lunar-cart`), orders (use `lunar-orders`), promotions/discounts (use `lunar-promotions`).

## Workflow

1. Identify the catalog feature needed (product CRUD, pricing, collections, etc.).
2. Read the appropriate reference in `references/`.
3. Use models directly for data access and the `Pricing` facade for price resolution.

## Core Concepts

### Product → Variant → Price Hierarchy

```
Product (attribute_data, status, brand)
  └── ProductVariant (sku, stock, dimensions, purchasable status)
        └── Price (amount, currency, customer_group, min_quantity)
```

Products always have at least one variant. Variants implement the `Purchasable` interface.

### Product Model

```php
use Lunar\Catalog\Models\Product;

$product = Product::create([
    'product_type_id' => $type->id,
    'name' => ['en' => 'Blue Widget'],
    'status' => 'draft',
]);

// Relationships
$product->variants;
$product->productType;
$product->brand;
$product->collections;
$product->productOptions;
$product->prices; // Prices across all variants
$product->associations; // Related/upsell/cross-sell
```

### Product States

Products and collections use state machines:
- **Draft** → Active, (no other transitions from draft except to Active)
- **Active** → Draft, Discontinued
- **Discontinued** → Draft

```php
$product->status->transitionTo(Active::class);
```

### ProductVariant and Purchasable Interface

```php
use Lunar\Catalog\Models\ProductVariant;

$variant = ProductVariant::create([
    'product_id' => $product->id,
    'sku' => 'WIDGET-BLUE-LG',
    'stock' => 100,
    'purchasable' => PurchasableStatus::Always,
    'tax_class_id' => $taxClass->id,
]);

// Purchasable interface methods
$variant->getPrices();
$variant->getUnitQuantity();
$variant->getTaxClass();
$variant->isShippable();
$variant->canBeFulfilledAtQuantity(5);
```

`PurchasableStatus` enum: `Always`, `InStock`, `Backorder`

### Pricing

```php
use Lunar\Catalog\Facades\Pricing;

// Basic price resolution
$result = Pricing::for($variant)->currency($currency)->get();
$result->matched;    // Best matching Price model
$result->base;       // Base price (min_quantity=1, no customer group)

// With customer groups and quantity
$result = Pricing::for($variant)
    ->currency($currency)
    ->qty(10)
    ->customerGroups($groups)
    ->get();

$result->priceBreaks;        // Quantity-based price tiers
$result->customerGroupPrices; // Group-specific prices

// Tax-aware pricing
$taxResult = Pricing::for($variant)
    ->currency($currency)
    ->region($region)
    ->getWithTax();

$taxResult->matched->priceIncTax;
$taxResult->matched->priceExcTax;
$taxResult->matched->taxAmount;
```

### Collections (Nested Sets)

```php
use Lunar\Catalog\Models\Collection;
use Lunar\Catalog\Models\CollectionGroup;

$group = CollectionGroup::create(['name' => 'Main Menu']);

$root = Collection::create([
    'collection_group_id' => $group->id,
    'name' => ['en' => 'Clothing'],
]);

$child = Collection::create([
    'collection_group_id' => $group->id,
    'name' => ['en' => 'T-Shirts'],
    'parent_id' => $root->id,
]);

// Tree operations (kalnoy/nestedset)
$root->children;
$child->parent;
$child->ancestors;
$root->descendants;

// Product attachment
$collection->products()->attach($product);
```

### Product Options

```php
use Lunar\Catalog\Models\ProductOption;

$option = ProductOption::create([
    'name' => ['en' => 'Size'],
    'handle' => 'size',
    'shared' => true, // Available across products
]);

$value = $option->values()->create([
    'name' => ['en' => 'Large'],
]);

// Attach to variant
$variant->options()->attach($value);
```

### Product Associations

```php
use Lunar\Catalog\Models\ProductAssociation;
use Lunar\Catalog\Enums\ProductAssociationType;

ProductAssociation::create([
    'product_id' => $product->id,
    'target_id' => $relatedProduct->id,
    'type' => ProductAssociationType::Related,
]);
```

Types: `Related`, `Upsell`, `CrossSell`

## Do and Don't

Do:
- Use the `Pricing` facade for price resolution — it handles currency, customer groups, and tax.
- Use product states (`Draft`, `Active`, `Discontinued`) for lifecycle management.
- Use `ProductType` to define attribute schemas via blueprints.
- Use nested set methods (`children`, `ancestors`, `descendants`) for collection trees.
- Use `shared` option flag to reuse product options across multiple products.

Don't:
- Don't query `Price` models directly for storefront display — use the `PricingManager` which applies business rules.
- Don't create products without a `ProductType` — it's required.
- Don't hard-code currency or customer group into price queries — resolve from `StorefrontContext`.
- Don't use model contracts or DI for models — Lunar v2 uses models directly.
- Don't manipulate `_lft`/`_rgt` columns on collections — use nested set methods.

## References

- `references/products-and-variants.md`
- `references/pricing.md`
- `references/collections.md`