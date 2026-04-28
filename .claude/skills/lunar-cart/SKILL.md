---
name: lunar-cart
description: Work with Lunar v2 cart operations including adding/removing items, cart calculation pipelines, shipping resolution, cart session management, and cart modifiers.
license: MIT
metadata:
  author: Lunar
---

# Lunar Cart Development

## Overview

The cart system manages shopping carts with a pipeline-based calculation architecture. Carts contain lines (purchasable items), addresses, and shipping selections. The `CartCalculator` runs a configurable pipeline to compute totals, taxes, and shipping. Cart session management handles persistence and user association.

## When to Activate

- Activate when working with cart CRUD operations (add, update, remove items).
- Activate when extending or customizing cart calculation pipelines.
- Activate when implementing shipping options or modifiers.
- Activate when code references `Cart`, `CartLine`, `CartAddress`, `CartSession`, `CartCalculator`, or `ShippingManifest`.

## Scope

- In scope: cart model, cart lines, cart addresses, cart actions, calculation pipelines, cart/line/shipping modifiers, cart session, shipping manifest.
- Out of scope: order creation (use `lunar-orders`), promotions (use `lunar-promotions`), payment processing (use `lunar-orders`).

## Workflow

1. Identify the cart operation needed.
2. Read the appropriate reference in `references/`.
3. Use cart methods or actions for operations, and pipeline extension for custom logic.

## Core Concepts

### Cart Model

```php
use Lunar\Sales\Models\Cart;

$cart = Cart::create([
    'currency_id' => $currency->id,
    'channel_id' => $channel->id,
    'region_id' => $region->id,
]);

// Key relationships
$cart->lines;            // HasMany CartLine
$cart->addresses;        // HasMany CartAddress
$cart->shippingAddress;  // HasOne (type=shipping)
$cart->billingAddress;   // HasOne (type=billing)
$cart->currency;
$cart->channel;
$cart->region;
$cart->user;
$cart->customer;
$cart->orders;
$cart->draftOrder;       // Order without placed_at
$cart->completedOrders;  // Orders with placed_at
```

### Cart Operations

```php
// Add or update a purchasable (e.g., ProductVariant)
$cart->add($variant, quantity: 2, meta: ['gift_wrap' => true]);

// Update a line
$cart->updateLine($cartLineId, quantity: 5);

// Remove a line
$cart->remove($cartLineId);

// Clear all lines
$cart->clear();

// Set addresses
$cart->setShippingAddress([
    'first_name' => 'John',
    'last_name' => 'Doe',
    'line_one' => '123 Main St',
    'city' => 'Springfield',
    'state' => 'IL',
    'postcode' => '62701',
    'country_id' => $country->id,
]);
$cart->setBillingAddress([...]);

// Set shipping option
$cart->setShippingOption('flat-rate');

// Calculate totals
$cart->calculate();
$cart->calculate(force: true); // Force recalculation

// Associate with user
$cart->associate($user, 'merge'); // merge or override

// Create order from cart
$order = $cart->createOrder();
```

### Cart Session Manager

```php
use Lunar\Sales\Facades\CartSession;

$cart = CartSession::current();        // Get or create session cart
CartSession::use($cart);               // Set cart as current
CartSession::forget();                 // Clear session cart
CartSession::associate($cart, $user, 'merge'); // Link to user

// Context
CartSession::setChannel($channel);
CartSession::setCurrency($currency);
CartSession::setRegion($region);
```

### Calculation Pipeline

The `CartCalculator` runs pipelines in order:

```
CalculateLines → ApplyShipping → CalculateTax → Calculate
```

Each pipeline receives the cart and passes it to the next stage.

#### Extending the Pipeline

```php
use Lunar\Sales\Calculators\CartCalculator;

// In a service provider
CartCalculator::addPipelineAfter(
    CalculateLines::class,
    MyCustomPipeline::class
);

CartCalculator::addPipelineBefore(
    CalculateTax::class,
    AnotherPipeline::class
);

CartCalculator::removePipeline(ApplyShipping::class);
```

### Cart Modifiers

```php
use Lunar\Sales\Pipelines\Cart\Contracts\CartModifier;

class MyCartModifier extends CartModifier
{
    public function calculating(Cart $cart): void
    {
        // Called before calculation
    }

    public function calculated(Cart $cart): void
    {
        // Called after calculation
    }
}
```

Similarly: `CartLineModifier`, `ShippingModifier`

### Shipping Manifest

```php
use Lunar\Sales\Contracts\ShippingManifestInterface;

// Resolve available shipping options for a cart
$options = app(ShippingManifestInterface::class)->getOptions($cart);
```

Payment/shipping modules (like table-rate-shipping) replace the default manifest.

### Cart Computed Properties (after calculate())

```php
$cart->sub_total;              // Line totals before tax/shipping
$cart->sub_total_discounted;   // After discounts
$cart->shipping_sub_total;     // Shipping before tax
$cart->shipping_tax_total;     // Shipping tax
$cart->shipping_total;         // Shipping after tax
$cart->tax_total;              // Total tax
$cart->discount_total;         // Total discounts
$cart->total;                  // Grand total
$cart->tax_breakdown;          // Tax detail array
$cart->shipping_breakdown;     // Shipping detail array
$cart->discount_breakdown;     // Discount detail array
```

## Do and Don't

Do:
- Use cart action methods (`add()`, `remove()`, `updateLine()`) instead of manipulating CartLine models directly.
- Call `$cart->calculate()` after modifications to update totals.
- Use `CartCalculator::addPipelineAfter/Before()` to extend calculation logic.
- Use `CartSession` facade for session-based cart management in web contexts.
- Check `$variant->canBeFulfilledAtQuantity()` for stock validation.

Don't:
- Don't set cart total fields directly — they are computed by the pipeline.
- Don't bypass the pipeline system by calculating totals manually.
- Don't create CartLine models directly — use `$cart->add()`.
- Don't hard-code shipping options — use the `ShippingManifestInterface`.
- Don't forget to set currency, channel, and region on the cart.

## References

- `references/cart-pipelines.md`
- `references/cart-actions.md`