# Discount Types Reference

## DiscountTypeInterface — `Lunar\Promotions\Contracts\DiscountTypeInterface`

```php
interface DiscountTypeInterface
{
    public function identifier(): string;
    public function apply(Discount $discount, Cart $cart): ?DiscountApplication;
    public function getProductDiscount(Discount $discount, CartLine $line): int;
}
```

## Built-in Types

### PercentageOff — `Lunar\Promotions\DiscountTypes\PercentageOff`

- **Identifier:** `percentage-off`
- **Data:** `['percentage' => float]`
- **Behavior:** Applies percentage discount to targeted lines. If no targets, applies to all lines.

### FixedAmountOff — `Lunar\Promotions\DiscountTypes\FixedAmountOff`

- **Identifier:** `fixed-amount-off`
- **Data:** `['amounts' => ['USD' => int, 'GBP' => int, ...]]`
- **Behavior:** Applies fixed currency amount off. Amount is in smallest currency unit. Distributed proportionally across targeted lines.

### BuyXGetY — `Lunar\Promotions\DiscountTypes\BuyXGetY`

- **Identifier:** `buy-x-get-y`
- **Data:** `['min_qty' => int, 'reward_qty' => int]`
- **Behavior:** When cart has min_qty of targeted products, reward_qty of cheapest get 100% discount.

### FreeShipping — `Lunar\Promotions\DiscountTypes\FreeShipping`

- **Identifier:** `free-shipping`
- **Data:** `['shipping_identifiers' => [string], 'max_amounts' => ['USD' => int]]`
- **Behavior:** Removes shipping cost. Optional: limit to specific shipping methods and cap discount amount per currency.

## Data Objects

### DiscountApplication

```php
new DiscountApplication(
    discount: Discount,
    totalDiscount: int,       // Total discount in smallest unit
    lines: Collection,        // Affected DiscountBreakdownLine objects
);
```

### DiscountBreakdownLine

```php
new DiscountBreakdownLine(
    line: CartLine,
    quantity: int,            // Quantity discounted
    amount: int,              // Discount amount per unit
);
```

## Custom Type Implementation

```php
use Lunar\Promotions\Contracts\DiscountTypeInterface;
use Lunar\Promotions\DataObjects\DiscountApplication;
use Lunar\Promotions\DataObjects\DiscountBreakdownLine;

class BundleDiscount implements DiscountTypeInterface
{
    public function identifier(): string
    {
        return 'bundle-discount';
    }

    public function apply(Discount $discount, Cart $cart): ?DiscountApplication
    {
        $bundleProducts = $discount->targets->pluck('targetable_id');
        $bundleLines = $cart->lines->filter(
            fn ($line) => $bundleProducts->contains($line->purchasable->product_id)
        );

        if ($bundleLines->count() < $bundleProducts->count()) {
            return null; // Not all bundle items in cart
        }

        $discountAmount = (int) ($discount->data['amount'] ?? 0);

        $breakdownLines = $bundleLines->map(fn ($line) => new DiscountBreakdownLine(
            line: $line,
            quantity: $line->quantity,
            amount: (int) ($discountAmount / $bundleLines->count()),
        ));

        return new DiscountApplication(
            discount: $discount,
            totalDiscount: $discountAmount,
            lines: $breakdownLines,
        );
    }

    public function getProductDiscount(Discount $discount, CartLine $line): int
    {
        return 0;
    }
}

// Register
Promotions::registerDiscountType(BundleDiscount::class);
```

## Registration

### Via Facade

```php
use Lunar\Promotions\Facades\Promotions;

Promotions::registerDiscountType(BundleDiscount::class);
```

### Via Config (`config/promotions.php`)

```php
'discount_types' => [
    \Lunar\Promotions\DiscountTypes\PercentageOff::class,
    \Lunar\Promotions\DiscountTypes\FixedAmountOff::class,
    \Lunar\Promotions\DiscountTypes\BuyXGetY::class,
    \Lunar\Promotions\DiscountTypes\FreeShipping::class,
    \App\Promotions\BundleDiscount::class,
],
```