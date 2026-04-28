---
name: lunar-stripe
description: Work with Lunar's Stripe integration including payment intent lifecycle, webhook handling, configuration, testing with mock clients, and customizing payment processing.
license: MIT
metadata:
  author: Lunar
---

# Lunar Stripe Development

## Overview

The Stripe package provides a payment driver for Lunar that manages Stripe payment intents. It handles intent creation and synchronization with carts, webhook processing for payment events, and automatic order placement on successful payment. The package uses a manager pattern with the `Stripe` facade for all Stripe API interactions.

## When to Activate

- Activate when configuring Stripe payment processing in a Lunar project.
- Activate when working with payment intents, checkout flows, or Stripe webhooks.
- Activate when code references `StripeManager`, `StripePaymentType`, `StripePaymentIntent`, or the `Stripe` facade.
- Activate when writing tests involving Stripe payments.

## Scope

- In scope: payment intent lifecycle, Stripe webhook handling, Stripe configuration, intent-cart association, testing with mock client.
- Out of scope: general cart operations (use `lunar-cart`), order management (use `lunar-orders`), other payment providers (use `lunar-paypal`).

## Workflow

1. Configure Stripe credentials in `config/stripe.php` or `.env`.
2. Frontend creates/fetches a payment intent via the API endpoint.
3. Customer completes payment on frontend using Stripe.js/Elements.
4. Stripe sends webhook → Lunar processes and places order.

## Core Concepts

### Configuration

In `.env`:
```
STRIPE_SECRET=sk_test_xxx
STRIPE_PUBLIC=pk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

In `config/stripe.php`:
```php
return [
    'secret' => env('STRIPE_SECRET'),
    'public_key' => env('STRIPE_PUBLIC'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'webhook_path' => 'stripe/webhook',
    'api_path' => 'api/stripe',
    'api_middleware' => ['api', 'auth:sanctum'],
    'policy' => 'automatic',   // or 'manual' for two-step capture
    'sync_addresses' => true,  // Sync cart addresses to Stripe
    'status_mapping' => [
        'succeeded' => 'payment-received',
        'requires_capture' => 'awaiting-capture',
        'payment_failed' => 'payment-failed',
    ],
];
```

### Stripe Facade — `Lunar\Stripe\Facades\Stripe`

#### Intent Lifecycle

```php
use Lunar\Stripe\Facades\Stripe;

// Create or fetch existing intent for cart
$intent = Stripe::fetchOrCreateIntent($cart, [
    'metadata' => ['order_notes' => 'Gift wrap'],
]);

// Create a new intent (always)
$intent = Stripe::createIntent($cart);

// Fetch existing intent by ID
$intent = Stripe::fetchIntent('pi_xxx');

// Sync intent amount with current cart total
Stripe::syncIntent($cart);

// Update intent with arbitrary values
Stripe::updateIntent($cart, ['metadata' => ['key' => 'value']]);
Stripe::updateIntentById('pi_xxx', ['metadata' => ['key' => 'value']]);

// Cancel intent
Stripe::cancelIntent($cart, CancellationReason::ABANDONED);

// Get active intent ID for cart
$intentId = Stripe::getCartIntentId($cart);
```

#### Charge and Payment Method Access

```php
$charges = Stripe::getCharges('pi_xxx');
$charge = Stripe::getCharge('ch_xxx');
$method = Stripe::getPaymentMethod('pm_xxx');
```

#### Raw Stripe SDK

```php
$client = Stripe::getClient();  // \Stripe\StripeClient instance
```

### StripePaymentIntent Model

Tracks intent state in the database:

| Field | Type | Notes |
|-------|------|-------|
| cart_id | foreignId | Associated cart |
| order_id | foreignId | Nullable, set after order creation |
| intent_id | string | Stripe payment intent ID |
| status | string | Current intent status |
| event_id | string | Nullable, last processed event |
| processing_at | datetime | Nullable, webhook processing start |
| processed_at | datetime | Nullable, webhook processing end |

### CancellationReason Enum

```php
use Lunar\Stripe\Enums\CancellationReason;

CancellationReason::DUPLICATE
CancellationReason::FRAUDULENT
CancellationReason::REQUESTED_BY_CUSTOMER
CancellationReason::ABANDONED
```

### Webhook Handling

Webhooks are processed asynchronously via the `ProcessStripeWebhook` job.

Handled events:
- `payment_intent.succeeded` — places order, records transaction
- `payment_intent.payment_failed` — updates order status

Webhook URL: `https://your-store.com/{webhook_path}` (default: `stripe/webhook`)

#### Customizing Webhook Processing

Bind custom implementations:
```php
$this->app->bind(
    \Lunar\Stripe\Contracts\ProcessesEventParameters::class,
    MyEventProcessor::class
);

$this->app->bind(
    \Lunar\Stripe\Contracts\ConstructsWebhookEvent::class,
    MyWebhookConstructor::class
);
```

### CartMissingForIntent Event

Fired when a webhook references a cart that no longer exists:
```php
use Lunar\Stripe\Events\CartMissingForIntent;

Event::listen(CartMissingForIntent::class, function ($event) {
    Log::warning("Cart missing for intent: {$event->intentId}");
});
```

### API Routes

- `POST {api_path}/payment-intent` — creates/fetches payment intent for cart
- `POST {webhook_path}` — Stripe webhook receiver

### Testing

```php
use Lunar\Stripe\Facades\Stripe;

// In test setup
Stripe::fake();

// Mock intent IDs for different scenarios
'PI_CAPTURE'                    // Successful capture
'PI_CAPTURE_LINK'               // Capture with payment link
'PI_FAIL'                       // Failed payment
'PI_REQUIRES_PAYMENT_METHOD'    // Needs payment method
'PI_REQUIRES_ACTION'            // Needs 3DS/authentication
'PI_FIRST_FAIL_THEN_CAPTURE'   // Fails first, succeeds on retry

// Set test config
config([
    'stripe.secret' => 'sk_test_fake',
    'stripe.public_key' => 'pk_test_fake',
    'stripe.webhook_secret' => 'whsec_fake',
]);
```

## Do and Don't

Do:
- Use `Stripe::fetchOrCreateIntent()` to get or create intents — it handles idempotency.
- Use `Stripe::syncIntent()` after cart modifications to update the intent amount.
- Set `STRIPE_WEBHOOK_SECRET` in production for webhook signature verification.
- Use `Stripe::fake()` in tests to avoid real API calls.
- Configure `status_mapping` to match your order status workflow.
- Listen for `CartMissingForIntent` to handle edge cases.

Don't:
- Don't create Stripe payment intents directly via the Stripe SDK — use the `Stripe` facade.
- Don't process webhooks synchronously — the package uses queued jobs.
- Don't store Stripe API keys in code — use environment variables.
- Don't manually update `StripePaymentIntent` model status — let the webhook flow handle it.
- Don't forget to run migrations for the `stripe_payment_intents` table.

## References

- `references/payment-intents.md`
- `references/webhooks.md`