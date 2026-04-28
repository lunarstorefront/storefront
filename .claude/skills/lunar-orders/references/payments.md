# Payments Reference

## PaymentManager — `Lunar\Sales\Managers\PaymentManager`

### Facade: `Lunar\Sales\Facades\Payments`

### Configuration

In `config/sales.php`:
```php
'payments' => [
    'default' => env('LUNAR_PAYMENT_DRIVER', 'offline'),
],
```

### Using Payment Drivers

```php
use Lunar\Sales\Facades\Payments;

// Default driver
$driver = Payments::driver();

// Specific driver
$driver = Payments::driver('stripe');
$driver = Payments::driver('paypal');
$driver = Payments::driver('offline');
```

## PaymentDriverInterface — `Lunar\Sales\Contracts\PaymentDriverInterface`

### Methods

```php
interface PaymentDriverInterface
{
    public function cart(Cart $cart): self;
    public function order(Order $order): self;
    public function withData(array $data): self;
    public function authorize(): PaymentAuthorize;
    public function capture(): PaymentCapture;
    public function refund(): PaymentRefund;
}
```

### Payment Flow

```php
// 1. Authorize payment
$response = Payments::driver('stripe')
    ->cart($cart)
    ->withData(['payment_intent' => 'pi_xxx'])
    ->authorize();

if ($response->success) {
    $orderId = $response->orderId;
}

// 2. Capture (for manual capture policies)
$response = Payments::driver('stripe')
    ->order($order)
    ->capture();

// 3. Refund
$response = Payments::driver('stripe')
    ->order($order)
    ->withData(['amount' => 500, 'notes' => 'Customer request'])
    ->refund();
```

### Response Objects

#### PaymentAuthorize

- `success` (bool)
- `orderId` (?int) — the created/updated order ID
- `message` (?string)

#### PaymentCapture

- `success` (bool)
- `transactionId` (?int)
- `message` (?string)

#### PaymentRefund

- `success` (bool)
- `transactionId` (?int)
- `message` (?string)

## Offline Payment Driver

Built-in driver for cash, bank transfer, or manual payments:
```php
$response = Payments::driver('offline')
    ->cart($cart)
    ->withData(['authorized_at' => now()])
    ->authorize();
```

## Custom Payment Driver

```php
use Lunar\Sales\Contracts\PaymentDriverInterface;

class BankTransferDriver implements PaymentDriverInterface
{
    protected ?Cart $cart = null;
    protected ?Order $order = null;
    protected array $data = [];

    public function cart(Cart $cart): self
    {
        $this->cart = $cart;
        return $this;
    }

    public function order(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function authorize(): PaymentAuthorize
    {
        // Create order from cart, record transaction
        $order = $this->cart->createOrder();

        return new PaymentAuthorize(
            success: true,
            orderId: $order->id,
        );
    }

    public function capture(): PaymentCapture { /* ... */ }
    public function refund(): PaymentRefund { /* ... */ }
}

// Register in service provider
Payments::extend('bank-transfer', function ($app) {
    return new BankTransferDriver();
});
```

## Transaction Model — `Lunar\Sales\Models\Transaction`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| order_id | foreignId | Required |
| type | string | TransactionType enum |
| amount | integer | Smallest currency unit |
| reference | string | External payment reference |
| status | string | Provider-specific status |
| driver | string | Payment driver used |
| card_type | string | Nullable |
| last_four | string | Nullable |
| meta | json | Additional data |
| success | boolean | Whether transaction succeeded |
| created_at | datetime | When recorded |

### TransactionType Enum

- `Payment`
- `Refund`
- `PartialRefund`

### Querying Transactions

```php
$order->transactions;   // All transactions
$order->captures;       // Type: Payment, success: true
$order->intents;        // Type: Payment, success: false (or pending)
$order->refunds;        // Type: Refund or PartialRefund
```