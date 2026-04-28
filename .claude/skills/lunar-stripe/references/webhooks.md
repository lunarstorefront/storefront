# Stripe Webhooks Reference

## Webhook Setup

### Stripe Dashboard

Create a webhook endpoint pointing to:
```
https://your-store.com/stripe/webhook
```

Subscribe to events:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`

### Configuration

```php
// config/stripe.php
'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
'webhook_path' => 'stripe/webhook',
```

## Processing Pipeline

```
HTTP Request
  → StripeWebhookMiddleware (signature verification)
    → WebhookController (dispatches job)
      → ProcessStripeWebhook job
        → ConstructWebhookEvent action
        → ProcessEventParameters action
        → UpdateOrderFromIntent action
        → StoreCharges action (if applicable)
        → StoreAddressInformation action (if sync_addresses enabled)
```

## Middleware — `StripeWebhookMiddleware`

Verifies the webhook signature using `STRIPE_WEBHOOK_SECRET`. Rejects requests with invalid signatures.

## Job — `ProcessStripeWebhook`

Queued job that processes webhook events. Handles idempotency via `event_id` on `StripePaymentIntent`.

## Key Actions

### ConstructWebhookEvent

Builds a Stripe event from the webhook payload.
```php
// Default implementation
Lunar\Stripe\Actions\ConstructWebhookEvent

// Custom binding
$this->app->bind(ConstructsWebhookEvent::class, MyConstructor::class);
```

### ProcessEventParameters

Extracts parameters from the event (intent ID, cart, order, etc.).
```php
// Returns EventParameters data object
Lunar\Stripe\DataObjects\EventParameters
```

### UpdateOrderFromIntent

Updates order status based on intent state. Uses `status_mapping` from config.

### StoreCharges

Records Stripe charges as `Transaction` records on the order.

### StoreAddressInformation

Syncs billing/shipping address from Stripe to the order (when `sync_addresses` is enabled).

## Events

### CartMissingForIntent

Fired when a webhook references a payment intent whose cart no longer exists.
```php
use Lunar\Stripe\Events\CartMissingForIntent;

// Properties
$event->intentId;  // Stripe payment intent ID

// Listener
Event::listen(CartMissingForIntent::class, function ($event) {
    // Handle missing cart (e.g., log, alert)
});
```

## Status Mapping

Maps Stripe payment intent statuses to Lunar order statuses:
```php
// config/stripe.php
'status_mapping' => [
    'succeeded' => 'payment-received',
    'requires_capture' => 'awaiting-capture',
    'payment_failed' => 'payment-failed',
    'canceled' => 'cancelled',
],
```

## Testing Webhooks

```php
Stripe::fake();

// Simulate webhook payload
$payload = [
    'type' => 'payment_intent.succeeded',
    'data' => [
        'object' => [
            'id' => 'PI_CAPTURE',
            'metadata' => ['cart_id' => $cart->id],
        ],
    ],
];

$this->post('/stripe/webhook', $payload);
```