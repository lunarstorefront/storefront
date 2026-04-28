# PayPal Orders Reference

## PayPalManager Methods

### createOrder

Creates a PayPal order from a cart.
```php
PayPal::createOrder(Cart $cart, array $opts = []): array
```
Returns PayPal API response with order ID, status, and approval links.

Common options:
```php
PayPal::createOrder($cart, [
    'return_url' => 'https://store.com/success',
    'cancel_url' => 'https://store.com/cancel',
]);
```

### getOrder

Retrieves a PayPal order by ID.
```php
PayPal::getOrder(string $orderId): array
```

### captureOrder

Captures an approved PayPal order.
```php
PayPal::captureOrder(string $orderId): array
```

### refundCapture

Refunds a captured payment.
```php
PayPal::refundCapture(string $captureId, int $amount, string $currencyCode): array
```
Amount is in smallest currency unit.

### getCartOrderId

Gets the active PayPal order ID for a cart.
```php
PayPal::getCartOrderId(Cart $cart): ?string
```

## Payment Flow

### Automatic Capture (default)

```
1. Frontend → POST /api/paypal/order (with cart)
2. Backend → PayPal::createOrder($cart)
3. Frontend → Redirect to PayPal approval URL
4. Buyer approves on PayPal
5. PayPal → POST /paypal/webhook (CHECKOUT.ORDER.APPROVED)
6. Backend → PayPal::captureOrder($orderId)
7. PayPal → POST /paypal/webhook (PAYMENT.CAPTURE.COMPLETED)
8. Backend → CreateOrder from cart, StoreCaptures as Transaction
```

### Manual Capture

```
1-5. Same as automatic
6. Admin reviews order
7. Admin → PayPal::captureOrder($orderId) (or via admin panel)
8-9. Same as steps 7-8 above
```

## PayPalPaymentType

Implements `PaymentDriverInterface`. Registered as `paypal` driver:

```php
// Authorize
Payments::driver('paypal')
    ->cart($cart)
    ->withData(['paypal_order_id' => $paypalOrderId])
    ->authorize();

// Refund
Payments::driver('paypal')
    ->order($order)
    ->withData(['amount' => 500])
    ->refund();
```

## PayPalOrder Model Scopes

```php
// Active orders (not COMPLETED or VOIDED)
PayPalOrder::active()->get();
```

## OAuth Token Management

The `PayPalManager` handles OAuth token acquisition and caching internally:
```php
PayPal::getAccessToken(): string  // Cached, auto-refreshed
PayPal::getApiUrl(): string       // 'https://api-m.sandbox.paypal.com' or 'https://api-m.paypal.com'
```

## Custom Store Captures Action

Override how PayPal captures are stored as transactions:
```php
// In config/paypal.php
'actions' => [
    'store_captures' => \App\Actions\MyStoreCaptures::class,
],
```