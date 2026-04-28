# PayPal Webhooks Reference

## Webhook Setup

### PayPal Developer Dashboard

Create a webhook endpoint pointing to:
```
https://your-store.com/paypal/webhook
```

Subscribe to events:
- `CHECKOUT.ORDER.COMPLETED`
- `CHECKOUT.ORDER.APPROVED`
- `PAYMENT.CAPTURE.COMPLETED`
- `PAYMENT.CAPTURE.DENIED`

Copy the Webhook ID to your `.env`:
```
PAYPAL_WEBHOOK_ID=your-webhook-id
```

### Configuration

```php
// config/paypal.php
'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
'webhook_path' => 'paypal/webhook',
```

## Processing Pipeline

```
HTTP Request
  → PayPalWebhookMiddleware (signature verification)
    → WebhookController (dispatches job)
      → ProcessPayPalWebhook job
        → ConstructWebhookEvent action
        → ProcessEventParameters action
        → UpdateOrderFromPayPalOrder action
        → StoreCaptures action (if applicable)
```

## Middleware — `PayPalWebhookMiddleware`

Verifies the webhook signature using `PAYPAL_WEBHOOK_ID` and PayPal's signature verification API.

## Job — `ProcessPayPalWebhook`

Queued job that processes webhook events. Handles idempotency via `event_id` on `PayPalOrder`.

## Key Actions

### ConstructWebhookEvent

Builds an event object from the webhook payload.
```php
// Custom binding
$this->app->bind(ConstructsWebhookEvent::class, MyConstructor::class);
```

### ProcessEventParameters

Extracts parameters from the event (PayPal order ID, cart, Lunar order, etc.).
```php
// Returns EventParameters data object
Lunar\PayPal\DataObjects\EventParameters
```

### UpdateOrderFromPayPalOrder

Updates Lunar order status based on PayPal order state.

### StoreCaptures

Records PayPal captures as `Transaction` records on the Lunar order.

## Events

### CartMissingForOrder

Fired when a webhook references a PayPal order whose cart no longer exists.
```php
use Lunar\PayPal\Events\CartMissingForOrder;

$event->paypalOrderId;  // PayPal order ID
```

## Status Mapping

Maps PayPal statuses to Lunar order statuses:
```php
// config/paypal.php
'status_mapping' => [
    'COMPLETED' => 'payment-received',
    'APPROVED' => 'awaiting-capture',
    'VOIDED' => 'cancelled',
    'PAYMENT.CAPTURE.DENIED' => 'payment-failed',
],
```

## Testing Webhooks

```php
PayPal::fake();

$payload = [
    'event_type' => 'CHECKOUT.ORDER.COMPLETED',
    'resource' => [
        'id' => 'PAYPAL_CAPTURE',
    ],
];

$this->post('/paypal/webhook', $payload);
```