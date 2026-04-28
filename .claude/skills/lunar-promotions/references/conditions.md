# Conditions Reference

## ConditionInterface — `Lunar\Promotions\Contracts\ConditionInterface`

```php
interface ConditionInterface
{
    public function identifier(): string;
    public function check(Discount $discount, Cart $cart): bool;
}
```

All conditions on a discount use AND logic — every condition must return `true` for the discount to apply.

## Built-in Conditions

### MinimumSpend — `Lunar\Promotions\Conditions\MinimumSpend`

- **Identifier:** `minimum-spend`
- **Data:** `['amount' => int, 'currency_code' => string]`
- **Behavior:** Cart sub_total must meet or exceed the specified amount (in smallest currency unit).

### MinimumQuantity — `Lunar\Promotions\Conditions\MinimumQuantity`

- **Identifier:** `minimum-quantity`
- **Data:** `['quantity' => int]`
- **Behavior:** Total quantity of targeted items must meet or exceed the specified quantity.

### ContainsProducts — `Lunar\Promotions\Conditions\ContainsProducts`

- **Identifier:** `contains-products`
- **Data:** `['product_ids' => [int], 'min_quantity' => int]`
- **Behavior:** Cart must contain at least `min_quantity` of any specified product.

### ContainsCollectionProducts — `Lunar\Promotions\Conditions\ContainsCollectionProducts`

- **Identifier:** `contains-collection-products`
- **Data:** `['collection_ids' => [int]]`
- **Behavior:** Cart must contain products belonging to any specified collection.

### ContainsBrandProducts — `Lunar\Promotions\Conditions\ContainsBrandProducts`

- **Identifier:** `contains-brand-products`
- **Data:** `['brand_ids' => [int]]`
- **Behavior:** Cart must contain products from any specified brand.

## ConditionEvaluator — `Lunar\Promotions\Support\ConditionEvaluator`

The evaluator resolves condition class instances and runs them:

```php
use Lunar\Promotions\Support\ConditionEvaluator;

$evaluator = app(ConditionEvaluator::class);
$passes = $evaluator->evaluate($discount, $cart);
```

## Custom Condition Implementation

```php
use Lunar\Promotions\Contracts\ConditionInterface;

class IsNewCustomer implements ConditionInterface
{
    public function identifier(): string
    {
        return 'is-new-customer';
    }

    public function check(Discount $discount, Cart $cart): bool
    {
        if (! $cart->user) {
            return false;
        }

        return $cart->user->orders()->doesntExist();
    }
}
```

### Registration

Via facade:
```php
Promotions::registerCondition(IsNewCustomer::class);
```

Via config (`config/promotions.php`):
```php
'conditions' => [
    \Lunar\Promotions\Conditions\MinimumSpend::class,
    \Lunar\Promotions\Conditions\MinimumQuantity::class,
    \Lunar\Promotions\Conditions\ContainsProducts::class,
    \Lunar\Promotions\Conditions\ContainsCollectionProducts::class,
    \Lunar\Promotions\Conditions\ContainsBrandProducts::class,
    \App\Promotions\IsNewCustomer::class,
],
```

## Coupon System

### PromotionCoupon Model

```php
$coupon = $promotion->coupons()->create([
    'code' => 'WELCOME10',
]);
```

### Applying Coupons

```php
$cart->update(['coupon_code' => 'WELCOME10']);
$cart->calculate();
```

### Usage Tracking

- `PromotionUsage` — tracks per-user promotion usage
- `PromotionCouponUse` — tracks per-user coupon usage
- Checked against `max_uses` and `max_uses_per_user` on Promotion

### Querying Promotions

```php
// Active promotions (within date range, not exhausted)
Promotion::active()->get();

// Usable promotions (active + within usage limits)
Promotion::usable()->get();

// Automatic (no coupons)
Promotion::automatic()->get();

// For a specific coupon code
Promotion::forCoupon('WELCOME10')->get();
```