<?php

use Illuminate\Support\Facades\Event;
use Lunar\Core\Events\Orders\OrderPlaced;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Data\Order as OrderData;
use Lunar\Storefront\Listeners\StampOrderLinePartNumbers;

beforeEach(function () {
    Currency::factory()->create(['code' => 'GBP', 'default' => true, 'decimal_places' => 2]);
    $this->order = Order::factory()->create(['currency_code' => 'GBP', 'placed_at' => null]);
});

/** A physical line on $order for a new variant with the given part number. */
function variantLineWithPartNumber(Order $order, ?string $mpn, array $attributes = []): OrderLine
{
    $variant = ProductVariant::factory()->for(Product::factory()->for(ProductType::factory()))->create(['mpn' => $mpn]);

    return OrderLine::factory()->for($order)->create([
        'type' => 'physical',
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        ...$attributes,
    ]);
}

test('it listens for orders being placed', function () {
    Event::fake();

    Event::assertListening(OrderPlaced::class, StampOrderLinePartNumbers::class);
});

test('a placed order keeps the part number it was sold under after the variant changes', function () {
    $line = variantLineWithPartNumber($this->order, '11100100');

    $this->order->update(['placed_at' => now()]);
    $line->purchasable->update(['mpn' => '11100200']);

    $data = OrderData::from($this->order->fresh()->load('physicalLines.purchasable'))->toArray();

    expect($line->fresh()->meta['mpn'])->toBe('11100100')
        ->and($data['physicalLines'][0]['mpn'])->toBe('11100100');
});

test('a line with no part number when placed keeps none after the variant gains one', function () {
    $line = variantLineWithPartNumber($this->order, null);

    $this->order->update(['placed_at' => now()]);
    $line->purchasable->update(['mpn' => '11100200']);

    $data = OrderData::from($this->order->fresh()->load('physicalLines.purchasable'))->toArray();

    expect($line->fresh()->meta->getArrayCopy())->toHaveKey('mpn')
        ->and($data['physicalLines'][0]['mpn'])->toBeNull();
});

test('it keeps the meta the line already has', function () {
    $line = variantLineWithPartNumber($this->order, '11100100', ['meta' => ['note' => 'gift']]);

    $this->order->update(['placed_at' => now()]);

    expect($line->fresh()->meta->getArrayCopy())->toBe(['note' => 'gift', 'mpn' => '11100100']);
});

test('it leaves a part number already on the line alone', function () {
    $line = variantLineWithPartNumber($this->order, '11100100', ['meta' => ['mpn' => 'OLD']]);

    $this->order->update(['placed_at' => now()]);

    expect($line->fresh()->meta['mpn'])->toBe('OLD');
});

test('it leaves shipping lines alone', function () {
    $product = variantLineWithPartNumber($this->order, '11100100');
    $shipping = OrderLine::factory()->for($this->order)->create([
        'type' => 'shipping',
        'purchasable_type' => null,
        'purchasable_id' => null,
    ]);

    $this->order->update(['placed_at' => now()]);

    expect($product->fresh()->meta['mpn'])->toBe('11100100')
        ->and($shipping->fresh()->meta)->toBeNull();
});
