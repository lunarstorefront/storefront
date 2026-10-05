<?php

use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Actions\Cart\AddOrderToCart;
use Lunar\Storefront\Data\ReorderResult;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    $this->channel = Channel::factory()->create(['default' => true]);
    $this->customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();
    TaxZone::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
        'language_id' => $language->id,
    ]);

    $this->order = Order::factory()->create(['placed_at' => now()]);
});

/**
 * A variant priced at today's price with the given stock, optionally
 * backorderable or retired.
 *
 * @param  array<string, mixed>  $attributes
 */
function reorderVariant(int $stock = 10, int $price = 1000, array $attributes = [], string $status = 'published', bool $purchasable = true): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(test()->productType)->state(['status' => $status]))
        ->for(test()->taxClass)
        ->inStock($stock)
        ->create([
            'sku' => 'SKU-'.uniqid(),
            'selling_policy' => SellingPolicy::InStock,
            ...$attributes,
        ]);

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => test()->currency->id,
        'price' => $price,
    ]);

    $variant->product->scheduleChannel(test()->channel);
    $variant->product->scheduleCustomerGroup(test()->customerGroup, pivotData: [
        'enabled' => true,
        'visible' => true,
        'purchasable' => $purchasable,
    ]);

    return $variant;
}

/**
 * A line on the past order, priced as it was then.
 */
function pastLine(?ProductVariant $variant, int $quantity = 2, string $identifier = 'OLD-SKU', string $description = 'Old part'): OrderLine
{
    return OrderLine::factory()->create([
        'order_id' => test()->order->id,
        'purchasable_type' => (new ProductVariant)->getMorphClass(),
        'purchasable_id' => $variant?->id ?? 999999,
        'identifier' => $variant?->sku ?? $identifier,
        'description' => $description,
        'quantity' => $quantity,
        'unit_price' => 1,
    ]);
}

function reorder(): ReorderResult
{
    return (new AddOrderToCart)->add(test()->order->fresh());
}

test('it adds every line to the cart at its past quantity', function () {
    $valve = reorderVariant();
    $pump = reorderVariant();
    pastLine($valve, quantity: 2);
    pastLine($pump, quantity: 3);

    $result = reorder();

    $cart = CartSession::current();

    expect($cart->lines)->toHaveCount(2)
        ->and($cart->lines->firstWhere('purchasable_id', $valve->id)->quantity)->toBe(2)
        ->and($cart->lines->firstWhere('purchasable_id', $pump->id)->quantity)->toBe(3)
        ->and($result->added)->toHaveCount(2)
        ->and($result->skipped)->toBeEmpty();
});

test('it prices the lines at today\'s price, not the historic one', function () {
    pastLine(reorderVariant(price: 2500), quantity: 1);

    reorder();

    expect(CartSession::current()->lines->first()->unitPrice->value)->toBe(2500);
});

test('it skips and flags a line that is now out of stock', function () {
    $valve = reorderVariant();
    pastLine($valve);
    pastLine(reorderVariant(stock: 0), description: 'Sold out pump');

    $result = reorder();

    expect(CartSession::current()->lines)->toHaveCount(1)
        ->and($result->skipped)->toHaveCount(1)
        ->and($result->skipped[0]->name)->toBe('Sold out pump')
        ->and($result->skipped[0]->reason)->toBe('out_of_stock');
});

test('it skips a line when there is not enough stock for the quantity', function () {
    pastLine(reorderVariant(stock: 1), quantity: 5);

    expect(reorder()->skipped[0]->reason)->toBe('out_of_stock');
});

test('it counts what the cart already holds against the stock', function () {
    $valve = reorderVariant(stock: 3);
    CartSession::add($valve, 2);
    pastLine($valve, quantity: 2);

    expect(reorder()->skipped[0]->reason)->toBe('out_of_stock')
        ->and(CartSession::current()->lines->first()->quantity)->toBe(2);
});

test('it skips and flags a retired product while still adding the rest', function () {
    pastLine(reorderVariant());
    pastLine(reorderVariant(status: 'draft'), description: 'Retired boiler');

    $result = reorder();

    expect(CartSession::current()->lines)->toHaveCount(1)
        ->and($result->skipped[0]->name)->toBe('Retired boiler')
        ->and($result->skipped[0]->reason)->toBe('unavailable');
});

test('it skips a disabled variant and one that no longer exists', function () {
    pastLine(reorderVariant(attributes: ['enabled' => false]), description: 'Disabled');
    pastLine(null, identifier: 'GONE-1', description: 'Deleted');

    $result = reorder();

    expect(collect($result->skipped)->pluck('reason')->all())->toBe(['unavailable', 'unavailable'])
        ->and(collect($result->skipped)->pluck('identifier')->all())->toContain('GONE-1');
});

test('it skips a product the customer group cannot buy', function () {
    pastLine(reorderVariant(purchasable: false), description: 'Trade only');

    $result = reorder();

    expect($result->skipped[0]->reason)->toBe('unavailable')
        ->and($result->added)->toBeEmpty();
});

test('it adds an out of stock line that can be backordered', function () {
    pastLine(reorderVariant(stock: 0, attributes: ['selling_policy' => SellingPolicy::Always]));

    $result = reorder();

    expect($result->added)->toHaveCount(1)
        ->and(CartSession::current()->lines)->toHaveCount(1);
});

test('it skips a line whose quantity no longer meets the variant rules', function () {
    pastLine(reorderVariant(attributes: ['min_quantity' => 5]), quantity: 2);

    expect(reorder()->skipped[0]->reason)->toBe('quantity');
});

test('it leaves the cart untouched when nothing can be reordered', function () {
    pastLine(reorderVariant(stock: 0));

    $result = reorder();

    expect($result->added)->toBeEmpty()
        ->and($result->skipped)->toHaveCount(1)
        ->and(Cart::query()->count())->toBe(0);
});

test('it adds alongside what the cart already holds', function () {
    $existing = reorderVariant();
    CartSession::add($existing, 1);
    pastLine(reorderVariant());

    reorder();

    expect(CartSession::current()->lines)->toHaveCount(2);
});

test('it ignores shipping lines', function () {
    pastLine(reorderVariant());
    OrderLine::withoutEvents(fn () => OrderLine::factory()->create([
        'order_id' => $this->order->id,
        'type' => 'shipping',
        'purchasable_type' => 'shippingoption',
        'purchasable_id' => 0,
    ]));

    $result = reorder();

    expect($result->added)->toHaveCount(1)
        ->and($result->skipped)->toBeEmpty();
});
