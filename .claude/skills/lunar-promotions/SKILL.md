---
name: lunar-promotions
description: Work with Lunar v2 promotions including discount types, conditions, coupons, exclusions, cart/order pipeline integration, and extending the promotion system with custom types and conditions.
license: MIT
metadata:
  author: Lunar
---

# Lunar Promotions Development

## Overview

The promotions module provides a flexible discount system. Promotions are campaigns containing one or more discounts. Each discount defines a type (how the discount is applied), conditions (eligibility rules), targets (what gets discounted), and exclusions (what's exempt). The system integrates with cart and order pipelines, and is fully extensible with custom discount types and conditions.

## When to Activate

- Activate when creating or managing promotions, discounts, or coupons.
- Activate when implementing custom discount types or conditions.
- Activate when working with discount targets, exclusions, or eligibility logic.
- Activate when code references `Promotion`, `Discount`, `PromotionManager`, `DiscountTypeInterface`, or `ConditionInterface`.

## Scope

- In scope: promotions, discounts, discount types, conditions, coupons, exclusions, promotion/discount states, cart and order pipeline integration, custom type/condition registration.
- Out of scope: cart calculation logic (use `lunar-cart`), payment processing (use `lunar-orders`).

## Workflow

1. Identify the promotion task (create promotion, add discount type, add condition, etc.).
2. Read the appropriate reference in `references/`.
3. Use models for data, `PromotionManager` for registration, and pipeline hooks for integration.

## Core Concepts

### Promotion Hierarchy

```
Promotion (campaign: name, dates, priority, max_uses)
  ├── Discount (type: percentage-off, data: {percentage: 10})
  │     ├── DiscountCondition (type: minimum-spend, data: {amount: 5000})
  │     ├── DiscountTarget (targetable: Product or Collection)
  │     └── DiscountExclusion (excludable: via ExclusionList)
  └── PromotionCoupon (code: 'SAVE10')
```

### Promotion Model

```php
use Lunar\Promotions\Models\Promotion;

$promotion = Promotion::create([
    'name' => ['en' => 'Summer Sale'],
    'handle' => 'summer-sale',
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'priority' => 1,
    'max_uses' => 1000,
    'max_uses_per_user' => 1,
    'exclusive' => false,
]);

// Automatic promotion (no coupon required)
// Add a coupon to make it coupon-based
$promotion->coupons()->create(['code' => 'SUMMER10']);
```

### Discount Model

```php
$discount = $promotion->discounts()->create([
    'type' => 'percentage-off',
    'data' => ['percentage' => 10],
    'priority' => 1,
]);

// Add conditions
$discount->conditions()->create([
    'type' => 'minimum-spend',
    'data' => ['amount' => 5000, 'currency_code' => 'USD'],
]);

// Add targets (empty = all products)
$discount->targets()->create([
    'targetable_type' => 'product',
    'targetable_id' => $product->id,
]);
```

### Built-in Discount Types

#### PercentageOff

```php
'type' => 'percentage-off',
'data' => ['percentage' => 15],  // 15% off
```

#### FixedAmountOff

```php
'type' => 'fixed-amount-off',
'data' => ['amounts' => ['USD' => 500, 'GBP' => 400]],  // Currency-keyed amounts
```

#### BuyXGetY

```php
'type' => 'buy-x-get-y',
'data' => ['min_qty' => 3, 'reward_qty' => 1],  // Buy 3 get 1 free
```

#### FreeShipping

```php
'type' => 'free-shipping',
'data' => [
    'shipping_identifiers' => ['flat-rate'],  // Optional: specific methods
    'max_amounts' => ['USD' => 1000],         // Optional: max discount per currency
],
```

### Built-in Conditions

#### MinimumSpend

```php
'type' => 'minimum-spend',
'data' => ['amount' => 5000, 'currency_code' => 'USD'],
```

#### MinimumQuantity

```php
'type' => 'minimum-quantity',
'data' => ['quantity' => 3],
```

#### ContainsProducts

```php
'type' => 'contains-products',
'data' => ['product_ids' => [1, 2, 3], 'min_quantity' => 1],
```

#### ContainsCollectionProducts

```php
'type' => 'contains-collection-products',
'data' => ['collection_ids' => [1, 2]],
```

#### ContainsBrandProducts

```php
'type' => 'contains-brand-products',
'data' => ['brand_ids' => [1]],
```

### Custom Discount Type

```php
use Lunar\Promotions\Contracts\DiscountTypeInterface;
use Lunar\Promotions\DataObjects\DiscountApplication;

class TieredPercentage implements DiscountTypeInterface
{
    public function identifier(): string
    {
        return 'tiered-percentage';
    }

    public function apply(Discount $discount, Cart $cart): ?DiscountApplication
    {
        // Custom logic to calculate discount
        return new DiscountApplication(
            discount: $discount,
            totalDiscount: $amount,
            lines: $affectedLines,
        );
    }

    public function getProductDiscount(Discount $discount, CartLine $line): int
    {
        // Per-line discount amount
        return 0;
    }
}
```

### Custom Condition

```php
use Lunar\Promotions\Contracts\ConditionInterface;

class IsFirstOrder implements ConditionInterface
{
    public function identifier(): string
    {
        return 'is-first-order';
    }

    public function check(Discount $discount, Cart $cart): bool
    {
        return $cart->user && $cart->user->orders()->count() === 0;
    }
}
```

### Registration

```php
use Lunar\Promotions\Facades\Promotions;

// In a service provider
Promotions::registerDiscountType(TieredPercentage::class);
Promotions::registerCondition(IsFirstOrder::class);
```

Or via `config/promotions.php`:
```php
'discount_types' => [
    TieredPercentage::class,
],
'conditions' => [
    IsFirstOrder::class,
],
```

### Cart Pipeline Integration

The promotions module automatically registers these cart pipeline stages:
- `ApplyDiscounts` — after `CalculateLines`, applies product/order discounts
- `ApplyShippingDiscount` — after `ApplyShipping`, applies shipping discounts

### Order Pipeline Integration

- `PersistDiscountBreakdown` — saves discount details to order
- `RecordPromotionUsage` — increments usage counters

### Promotion States

- `Draft` → `Active` → `Archived`
- Same transitions as Product states

## Do and Don't

Do:
- Use `PromotionManager::registerDiscountType()` for custom discount types.
- Use `PromotionManager::registerCondition()` for custom conditions.
- Use AND logic for conditions — all must pass for discount to apply.
- Use targets to scope discounts to specific products, collections, or brands.
- Use exclusion lists to exempt products from promotions.
- Set `max_uses` and `max_uses_per_user` to prevent abuse.

Don't:
- Don't apply discounts manually to cart totals — use the pipeline system.
- Don't bypass the `ConditionEvaluator` — it handles all condition checking.
- Don't hard-code discount amounts — use currency-keyed data for multi-currency.
- Don't create discount types without implementing `DiscountTypeInterface`.
- Don't forget to set `starts_at`/`ends_at` for time-limited promotions.

## References

- `references/discount-types.md`
- `references/conditions.md`