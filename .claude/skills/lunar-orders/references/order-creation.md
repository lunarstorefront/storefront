# Order Creation Reference

## CreateOrder Action

Creates an order from a fully calculated cart. Runs the order creation pipeline.

### Usage

```php
$order = $cart->createOrder();

// Allow multiple orders from same cart (e.g., split orders)
$order = $cart->createOrder(allowMultiple: true);
```

### Prerequisites

- Cart must have a currency, channel, and region set.
- Cart should be calculated (`$cart->calculate()`).
- Cart should have at least one line.

### Order Creation Pipeline

Managed by `OrderPipelineManager`. Default stages:

1. **FillOrderFromCart** — copies cart metadata to order fields (sub_total, tax_total, total, currency_code, exchange_rate, fingerprint, etc.)
2. **CreateOrderLines** — creates OrderLine records from CartLines with type, description, pricing, and purchasable reference
3. **CreateOrderAddresses** — copies CartAddress records to OrderAddress
4. **CreateShippingLine** — creates an OrderLine of type `shipping` from the selected shipping option
5. **CleanUpOrderLines** — removes orphaned or invalid lines

### OrderLine Types — `Lunar\Sales\Enums\OrderLineType`

- `Physical` — tangible product
- `Digital` — digital product
- `Shipping` — shipping charge

### Order Model Fields (after creation)

| Field | Type | Notes |
|-------|------|-------|
| customer_id | foreignId | From cart |
| user_id | foreignId | From cart |
| cart_id | foreignId | Source cart |
| channel_id | foreignId | From cart |
| status | string | Default: first configured status |
| reference | string | Auto-generated, unique |
| sub_total | integer | Smallest currency unit |
| discount_total | integer | Applied discounts |
| shipping_total | integer | Shipping cost |
| tax_total | integer | Total tax |
| total | integer | Grand total |
| currency_code | string | E.g., 'USD' |
| exchange_rate | float | Currency exchange rate |
| placed_at | datetime | Null until payment captured |
| meta | json | Custom metadata |

### Extending the Pipeline

```php
use Lunar\Sales\Calculators\OrderPipelineManager;

// Add custom stage
OrderPipelineManager::addPipelineAfter(
    CreateOrderLines::class,
    AddLoyaltyPoints::class
);

// Custom stage implementation
class AddLoyaltyPoints
{
    public function handle(Order $order, Closure $next): Order
    {
        // Custom logic
        return $next($order);
    }
}
```

### GenerateOrderReference

Default implementation: zero-padded order ID.

Custom implementation:
```php
use Lunar\Sales\Contracts\OrderReferenceGeneratorInterface;

class CustomReference implements OrderReferenceGeneratorInterface
{
    public function generate(Order $order): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . $order->id;
    }
}

// In AppServiceProvider
$this->app->bind(OrderReferenceGeneratorInterface::class, CustomReference::class);
```