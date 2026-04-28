# Payment Intents Reference

## StripeManager Methods

### fetchOrCreateIntent

Creates a new intent or returns the existing one for a cart.
```php
Stripe::fetchOrCreateIntent(Cart $cart, array $opts = []): \Stripe\PaymentIntent
```
Options are passed to `Stripe\PaymentIntent::create()`.

### createIntent

Always creates a new intent, cancelling any existing one.
```php
Stripe::createIntent(Cart $cart, array $opts = []): \Stripe\PaymentIntent
```

### fetchIntent

Retrieves an intent from Stripe by ID.
```php
Stripe::fetchIntent(string $intentId): \Stripe\PaymentIntent
```

### syncIntent

Updates the intent amount to match the current cart total.
```php
Stripe::syncIntent(Cart $cart): void
```

### updateIntent

Updates arbitrary values on the intent associated with a cart.
```php
Stripe::updateIntent(Cart $cart, array $values): \Stripe\PaymentIntent
```

### updateIntentById

Updates arbitrary values on an intent by its ID.
```php
Stripe::updateIntentById(string $intentId, array $values): \Stripe\PaymentIntent
```

### cancelIntent

Cancels the active intent for a cart.
```php
Stripe::cancelIntent(Cart $cart, CancellationReason $reason): \Stripe\PaymentIntent
```

### getCartIntentId

Gets the active payment intent ID for a cart.
```php
Stripe::getCartIntentId(Cart $cart): ?string
```

### getClient

Returns the underlying Stripe SDK client.
```php
Stripe::getClient(): \Stripe\StripeClient
```

### Charge Access

```php
Stripe::getCharges(string $intentId): \Stripe\Collection
Stripe::getCharge(string $chargeId): \Stripe\Charge
Stripe::getPaymentMethod(string $methodId): \Stripe\PaymentMethod
```

## Payment Flow

### Automatic Capture (default)

```
1. Frontend → POST /api/stripe/payment-intent (with cart)
2. Backend → Stripe::fetchOrCreateIntent($cart)
3. Frontend → stripe.confirmCardPayment(clientSecret)
4. Stripe → POST /stripe/webhook (payment_intent.succeeded)
5. Backend → ProcessStripeWebhook job
   → CreateOrder from cart
   → StoreCharges as Transaction
   → Set order placed_at
```

### Manual Capture

```
1-3. Same as automatic
4. Stripe → POST /stripe/webhook (payment_intent.succeeded with requires_capture)
5. Backend → Order created with 'awaiting-capture' status
6. Admin → Payments::driver('stripe')->order($order)->capture()
7. Stripe → POST /stripe/webhook (charge.captured)
```

## StripePaymentType

Implements `PaymentDriverInterface`. Registered as `stripe` driver:

```php
// Authorize (called by webhook processing)
Payments::driver('stripe')
    ->cart($cart)
    ->withData(['payment_intent' => $intentId])
    ->authorize();

// Capture (manual capture policy)
Payments::driver('stripe')
    ->order($order)
    ->withData(['amount' => $order->total])
    ->capture();

// Refund
Payments::driver('stripe')
    ->order($order)
    ->withData(['amount' => 500, 'notes' => 'Partial refund'])
    ->refund();
```

## Custom Store Charges Action

Override how Stripe charges are stored as transactions:
```php
// In config/stripe.php
'actions' => [
    'store_charges' => \App\Actions\MyStoreCharges::class,
],
```