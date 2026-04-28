---
name: lunar-orders
description: Work with Lunar v2 order creation from carts, payment processing with pluggable drivers, transactions, order pipelines, and order reference generation.
license: MIT
metadata:
  author: Lunar
---

# Lunar Orders Development

## Overview

Orders are created from carts via the `CreateOrder` action, which runs a multi-stage pipeline to copy cart data into an order. Payment processing uses a driver-based `PaymentManager` that supports multiple payment providers. The offline payment driver is built-in; Stripe and PayPal are separate packages.

## When to Activate

- Activate when creating orders from carts.
- Activate when implementing or extending payment drivers.
- Activate when working with order statuses, references, or transactions.
- Activate when code references `Order`, `OrderLine`, `Transaction`, `PaymentManager`, or the `Payments` facade.

## Scope

- In scope: order model, order lines, order addresses, transactions, order creation pipeline, payment drivers, order reference generation, order statuses.
- Out of scope: cart operations (use `lunar-cart`), Stripe/PayPal specifics (use `lunar-stripe`/`lunar-paypal`), promotions (use `lunar-promotions`).

## Workflow

1. Cart is fully calculated with lines, addresses, and shipping.
2. `$cart->createOrder()` runs the order creation pipeline.
3. Payment is processed via `Payments::driver('stripe')->authorize()` (or similar).
4. Transaction is recorded.

## Core Concepts

### Creating an Order

```php
// From a cart (most common)
$order = $cart->createOrder();

// Allow multiple orders from same cart
$order = $cart->createOrder(allowMultiple: true);
```

### Order Model

```php
use Lunar\Sales\Models\Order;

$order->reference;        // Unique order reference (auto-generated)
$order->status;           // String status (e.g., 'awaiting-payment')
$order->isDraft();        // placed_at is null
$order->isPlaced();       // placed_at is set

// Relationships
$order->lines;            // HasMany OrderLine
$order->productLines;     // Filtered by type
$order->shippingLines;    // Filtered by type
$order->addresses;        // HasMany OrderAddress
$order->shippingAddress;  // HasOne (type=shipping)
$order->billingAddress;   // HasOne (type=billing)
$order->transactions;     // HasMany Transaction
$order->captures;         // Filtered transactions
$order->intents;          // Filtered transactions
$order->refunds;          // Filtered transactions
$order->cart;             // BelongsTo Cart
$order->channel;
$order->customer;
$order->user;
$order->currency;         // Resolved from currency_code
```

### Order Statuses

Configured in `config/sales.php`:
```php
'statuses' => [
    'awaiting-payment' => ['label' => 'Awaiting Payment', 'color' => '#e5e7eb'],
    'payment-received' => ['label' => 'Payment Received', 'color' => '#6ee7b7'],
    // ...
],
```

### Order Reference Generator

Default generates zero-padded order IDs. Customize:
```php
use Lunar\Sales\Contracts\OrderReferenceGeneratorInterface;

class MyReferenceGenerator implements OrderReferenceGeneratorInterface
{
    public function generate(Order $order): string
    {
        return 'ORD-' . str_pad($order->id, 8, '0', STR_PAD_LEFT);
    }
}

// Bind in service provider
$this->app->bind(OrderReferenceGeneratorInterface::class, MyReferenceGenerator::class);
```

### Payment Manager

```php
use Lunar\Sales\Facades\Payments;

// Authorize payment
$response = Payments::driver('stripe')
    ->cart($cart)
    ->withData(['payment_intent' => $intentId])
    ->authorize();

$response->success;   // bool
$response->orderId;   // int (if order created/placed)
$response->message;   // string

// Capture (for manual capture flows)
$response = Payments::driver('stripe')
    ->order($order)
    ->capture();

// Refund
$response = Payments::driver('stripe')
    ->order($order)
    ->withData(['amount' => 1000])
    ->refund();
```

### Custom Payment Driver

```php
use Lunar\Sales\Contracts\PaymentDriverInterface;

class MyPaymentDriver implements PaymentDriverInterface
{
    public function cart(Cart $cart): self { /* ... */ }
    public function order(Order $order): self { /* ... */ }
    public function withData(array $data): self { /* ... */ }
    public function authorize(): PaymentAuthorize { /* ... */ }
    public function capture(): PaymentCapture { /* ... */ }
    public function refund(): PaymentRefund { /* ... */ }
}

// Register in service provider
Payments::extend('my-gateway', function ($app) {
    return new MyPaymentDriver();
});
```

### Transaction Model

```php
$transaction->order;       // BelongsTo Order
$transaction->type;        // TransactionType enum
$transaction->amount;      // int (smallest currency unit)
$transaction->reference;   // External reference
$transaction->status;      // Provider-specific status
$transaction->card_type;   // Nullable
$transaction->last_four;   // Nullable
$transaction->meta;        // JSON
```

### TransactionType Enum

- `Payment` — money captured
- `Refund` — full refund
- `PartialRefund` — partial refund

### Order Creation Pipeline

```
FillOrderFromCart → CreateOrderLines → CreateOrderAddresses → CreateShippingLine → CleanUpOrderLines
```

Extend via `OrderPipelineManager`:
```php
use Lunar\Sales\Calculators\OrderPipelineManager;

OrderPipelineManager::addPipelineAfter(CreateOrderLines::class, MyOrderStage::class);
```

### OrderModifier

```php
use Lunar\Sales\Pipelines\Order\Contracts\OrderModifier;

class MyOrderModifier extends OrderModifier
{
    public function creating(Order $order, Cart $cart): void { }
    public function created(Order $order, Cart $cart): void { }
}
```

### Events

- `CartCreated`, `CartUpdated`, `CartDeleted`
- `OrderCreated` — fired after order creation pipeline completes
- `OrderPlaced` — fired when placed_at is set
- `PaymentCaptured`, `PaymentRefunded`

## Do and Don't

Do:
- Use `$cart->createOrder()` to create orders — it runs the full pipeline.
- Use the `Payments` facade with driver pattern for payment processing.
- Implement `PaymentDriverInterface` for custom payment gateways.
- Configure order statuses in `config/sales.php`.
- Use `OrderReferenceGeneratorInterface` to customize reference format.

Don't:
- Don't create Order models directly — use the cart-to-order pipeline.
- Don't hard-code payment logic — use payment drivers.
- Don't set `placed_at` manually — payment drivers handle this.
- Don't modify order line totals directly — they come from the creation pipeline.
- Don't skip the pipeline by copying cart fields to order manually.

## References

- `references/order-creation.md`
- `references/payments.md`