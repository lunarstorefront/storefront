---
name: lunar-paypal
description: Work with Lunar's PayPal integration including order lifecycle, webhook handling, configuration, testing with mock clients, and customizing payment processing.
license: MIT
metadata:
  author: Lunar
---

# Lunar PayPal Development

## Overview

The PayPal package provides a payment driver for Lunar that manages PayPal orders. It handles order creation linked to carts, capturing approved payments, webhook processing for payment events, and automatic Lunar order placement. The package uses a manager pattern with the `PayPal` facade and handles OAuth token management internally.

## When to Activate

- Activate when configuring PayPal payment processing in a Lunar project.
- Activate when working with PayPal orders, checkout flows, or PayPal webhooks.
- Activate when code references `PayPalManager`, `PayPalPaymentType`, `PayPalOrder`, or the `PayPal` facade.
- Activate when writing tests involving PayPal payments.

## Scope

- In scope: PayPal order lifecycle, webhook handling, PayPal configuration, order-cart association, testing with mock client.
- Out of scope: general cart operations (use `lunar-cart`), order management (use `lunar-orders`), Stripe payments (use `lunar-stripe`).

## Workflow

1. Configure PayPal credentials in `config/paypal.php` or `.env`.
2. Frontend creates a PayPal order via the API endpoint.
3. Customer approves payment on PayPal.
4. PayPal sends webhook → Lunar captures and places order.

## Core Concepts

### Configuration

In `.env`:
```
PAYPAL_CLIENT_ID=your-client-id
PAYPAL_SECRET=your-secret
PAYPAL_ENVIRONMENT=sandbox   # or 'live'

PAYPAL_WEBHOOK_ID=your-webhook-id
```

In `config/paypal.php`:
```php
return [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'secret' => env('PAYPAL_SECRET'),
    'environment' => env('PAYPAL_ENVIRONMENT', 'sandbox'),
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    'webhook_path' => 'paypal/webhook',
    'api_path' => 'api/paypal',
    'api_middleware' => ['api', 'auth:sanctum'],
    'policy' => 'automatic',   // or 'manual' for two-step capture
    'status_mapping' => [
        'COMPLETED' => 'payment-received',
        'APPROVED' => 'awaiting-capture',
        'VOIDED' => 'cancelled',
    ],
];
```

### PayPal Facade — `Lunar\PayPal\Facades\PayPal`

#### Order Lifecycle

```php
use Lunar\PayPal\Facades\PayPal;

// Create PayPal order for cart
$response = PayPal::createOrder($cart, [
    'return_url' => 'https://store.com/checkout/success',
    'cancel_url' => 'https://store.com/checkout/cancel',
]);
// Returns: ['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED', 'links' => [...]]

// Get PayPal order details
$order = PayPal::getOrder('PAYPAL_ORDER_ID');

// Capture approved order
$capture = PayPal::captureOrder('PAYPAL_ORDER_ID');

// Refund a capture
$refund = PayPal::refundCapture('CAPTURE_ID', 500, 'USD');

// Get active PayPal order ID for cart
$orderId = PayPal::getCartOrderId($cart);
```

#### API Access

```php
// Get API base URL
$url = PayPal::getApiUrl();  // sandbox or live

// Get OAuth access token (cached)
$token = PayPal::getAccessToken();

// Verify webhook signature
$valid = PayPal::verifyWebhookSignature($headers, $body);
```

### PayPalOrder Model

Tracks PayPal order state in the database:

| Field | Type | Notes |
|-------|------|-------|
| cart_id | foreignId | Associated cart |
| order_id | foreignId | Nullable, Lunar order ID after creation |
| paypal_order_id | string | PayPal order identifier |
| status | string | Current PayPal order status |
| event_id | string | Nullable, last processed event ID |
| processing_at | datetime | Nullable, webhook processing start |
| processed_at | datetime | Nullable, webhook processing end |

### Webhook Handling

Webhooks are processed asynchronously via the `ProcessPayPalWebhook` job.

Handled events:
- `CHECKOUT.ORDER.COMPLETED` — places Lunar order, records transaction
- `CHECKOUT.ORDER.APPROVED` — marks order as approved
- `PAYMENT.CAPTURE.COMPLETED` — records successful capture
- `PAYMENT.CAPTURE.DENIED` — updates order status to failed

Webhook URL: `https://your-store.com/{webhook_path}` (default: `paypal/webhook`)

#### Customizing Webhook Processing

```php
$this->app->bind(
    \Lunar\PayPal\Contracts\ProcessesEventParameters::class,
    MyEventProcessor::class
);

$this->app->bind(
    \Lunar\PayPal\Contracts\ConstructsWebhookEvent::class,
    MyWebhookConstructor::class
);
```

### CartMissingForOrder Event

```php
use Lunar\PayPal\Events\CartMissingForOrder;

Event::listen(CartMissingForOrder::class, function ($event) {
    Log::warning("Cart missing for PayPal order: {$event->paypalOrderId}");
});
```

### API Routes

- `POST {api_path}/order` — creates PayPal order for cart
- `POST {webhook_path}` — PayPal webhook receiver

### Testing

```php
use Lunar\PayPal\Facades\PayPal;

// In test setup
PayPal::fake();

// Mock order IDs for different scenarios
'PAYPAL_CAPTURE'          // Successful capture
'PAYPAL_FAIL'             // Failed payment
'PAYPAL_APPROVED'         // Approved, awaiting capture
'PAYPAL_REQUIRES_ACTION'  // Needs buyer action

// Set test config
config([
    'paypal.client_id' => 'test-client-id',
    'paypal.secret' => 'test-secret',
    'paypal.environment' => 'sandbox',
    'paypal.webhook_id' => 'test-webhook-id',
]);
```

## Do and Don't

Do:
- Use `PayPal::createOrder()` to create PayPal orders — it handles cart association and amount calculation.
- Use `PayPal::captureOrder()` after buyer approval to capture payment.
- Set `PAYPAL_WEBHOOK_ID` in production for webhook signature verification.
- Use `PayPal::fake()` in tests to avoid real API calls.
- Configure `status_mapping` to match your order status workflow.

Don't:
- Don't make direct PayPal API calls — use the `PayPal` facade.
- Don't process webhooks synchronously — the package uses queued jobs.
- Don't store PayPal credentials in code — use environment variables.
- Don't manually update `PayPalOrder` model status — let the webhook flow handle it.
- Don't forget to run migrations for the `paypal_orders` table.

## References

- `references/paypal-orders.md`
- `references/webhooks.md`