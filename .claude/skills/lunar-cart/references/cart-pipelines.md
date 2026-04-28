# Cart Pipelines Reference

## CartCalculator — `Lunar\Sales\Calculators\CartCalculator`

### Default Pipeline Order

1. `CalculateLines` — resolves unit prices and line totals
2. `ApplyShipping` — resolves and applies shipping costs
3. `CalculateTax` — calculates tax on lines and shipping
4. `Calculate` — sums up final totals

### Pipeline Extension

```php
use Lunar\Sales\Calculators\CartCalculator;

// Add after a specific stage
CartCalculator::addPipelineAfter(CalculateLines::class, MyStage::class);

// Add before a specific stage
CartCalculator::addPipelineBefore(CalculateTax::class, MyStage::class);

// Remove a stage
CartCalculator::removePipeline(ApplyShipping::class);

// Add to end
CartCalculator::addPipeline(MyStage::class);
```

### Writing a Pipeline Stage

```php
use Closure;
use Lunar\Sales\Models\Cart;

class MyCartPipeline
{
    public function handle(Cart $cart, Closure $next): Cart
    {
        // Modify cart before passing to next stage
        // Access: $cart->lines, $cart->addresses, etc.

        return $next($cart);
    }
}
```

## CartLineCalculator — `Lunar\Sales\Calculators\CartLineCalculator`

### Default Pipeline

1. `GetUnitPrice` — resolves price from PricingManager

### Extension

```php
CartLineCalculator::addPipeline(MyLineStage::class);
```

## Cart Modifiers

### CartModifier

```php
use Lunar\Sales\Pipelines\Cart\Contracts\CartModifier;

class MyCartModifier extends CartModifier
{
    public function calculating(Cart $cart): void { }
    public function calculated(Cart $cart): void { }
}
```

### CartLineModifier

```php
use Lunar\Sales\Pipelines\Cart\Contracts\CartLineModifier;

class MyLineModifier extends CartLineModifier
{
    public function calculating(CartLine $line): void { }
    public function calculated(CartLine $line): void { }
}
```

### ShippingModifier

```php
use Lunar\Sales\Pipelines\Cart\Contracts\ShippingModifier;

class MyShippingModifier extends ShippingModifier
{
    public function handle(Cart $cart): void { }
}
```

## Cart Prune Pipeline

Used by `lunar:prune-carts` command:
1. `WhereNotMerged` — exclude merged carts
2. `WithoutOrders` — exclude carts with orders
3. `PruneAfter` — apply TTL (configurable in `config/sales.php`)

## OrderPipelineManager — `Lunar\Sales\Calculators\OrderPipelineManager`

### Default Pipeline (for order creation)

1. `FillOrderFromCart` — copy cart data to order
2. `CreateOrderLines` — create order lines from cart lines
3. `CreateOrderAddresses` — copy addresses
4. `CreateShippingLine` — create shipping line item
5. `CleanUpOrderLines` — remove invalid lines

### Extension

```php
use Lunar\Sales\Calculators\OrderPipelineManager;

OrderPipelineManager::addPipelineAfter(CreateOrderLines::class, MyStage::class);
```