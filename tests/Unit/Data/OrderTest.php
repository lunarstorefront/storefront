<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress as OrderAddressModel;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\States\Order\Payment\Paid;
use Lunar\Storefront\Data\Order as OrderData;
use Lunar\Storefront\Data\OrderAddress;

beforeEach(function () {
    Currency::factory()->create(['code' => 'GBP', 'default' => true, 'decimal_places' => 2]);
    $this->order = Order::factory()->create([
        'reference' => '00000001',
        'customer_reference' => 'PO-4471',
        'notes' => 'Leave at the side gate',
        'placed_at' => now(),
    ]);
    $this->order->update(['payment_status' => Paid::class]);
});

test('it maps a placed order', function () {
    $data = OrderData::from($this->order->fresh())->toArray();

    expect($data)
        ->id->toBe((string) $this->order->id)
        ->reference->toBe('00000001')
        ->customerReference->toBe('PO-4471')
        ->notes->toBe('Leave at the side gate')
        ->paymentStatus->toBe('paid')
        ->fulfilmentStatus->toBe('unfulfilled')
        ->status->toBe('unfulfilled')
        ->placedAt->toBeString();
});

test('it carries the payment method the checkout stamped on the order', function () {
    $this->order->update(['meta' => ['payment_method' => 'on-account']]);

    expect(OrderData::from($this->order->fresh())->paymentMethod)->toBe('on-account');
});

test('it leaves the payment method unknown when none was stamped', function (?array $meta) {
    $this->order->update(['meta' => $meta]);

    expect(OrderData::from($this->order->fresh())->paymentMethod)->toBeNull();
})->with([
    'no meta' => [null],
    'no method' => [['source' => 'import']],
    'blank method' => [['payment_method' => '']],
]);

test('it accepts the immutable dates an app can opt into', function () {
    Date::use(CarbonImmutable::class);

    $order = $this->order->fresh();

    expect($order->placed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and(OrderData::from($order)->placedAt?->getTimestamp())->toBe($order->placed_at->getTimestamp());

    Date::useDefault();
});

test('it maps loaded addresses as order addresses, with their country', function () {
    $country = Country::factory()->create(['iso2' => 'GB', 'name' => 'United Kingdom']);
    OrderAddressModel::factory()->for($this->order)->create([
        'type' => 'billing',
        'postcode' => 'BL9 0SA',
        'country_id' => $country->id,
    ]);

    $order = $this->order->fresh()->load('billingAddress.country', 'shippingAddress');
    $data = OrderData::from($order)->toArray();

    expect(OrderAddress::fromModel($order->billingAddress))->toBeInstanceOf(OrderAddress::class)
        ->and($data['billingAddress']['postcode'])->toBe('BL9 0SA')
        ->and($data['billingAddress']['countryIso'])->toBe('GB')
        ->and($data['shippingAddress'])->toBeNull();
});

test('it carries the shipping lines when loaded', function () {
    OrderLine::factory()->for($this->order)->create([
        'type' => 'shipping',
        'description' => 'Click & collect',
        'identifier' => 'COLLECTION',
    ]);
    OrderLine::factory()->for($this->order)->create(['type' => 'physical']);

    $data = OrderData::from($this->order->fresh()->load('shippingLines', 'physicalLines'))->toArray();

    expect($data['shippingLines'])->toHaveCount(1)
        ->and($data['shippingLines'][0]['description'])->toBe('Click & collect')
        ->and($data['physicalLines'])->toHaveCount(1);
});

test('it leaves relations out unless they are loaded', function () {
    $data = OrderData::from($this->order->fresh())->toArray();

    expect($data)->not->toHaveKeys(['billingAddress', 'shippingAddress', 'shippingLines', 'physicalLines']);
});

test('it formats the totals in the order currency, as the cart does', function () {
    $this->order->update([
        'sub_total' => 1250,
        'discount_total' => 0,
        'shipping_total' => 495,
        'tax_total' => 349,
        'total' => 2094,
        'currency_code' => 'GBP',
    ]);
    OrderLine::factory()->for($this->order)->create([
        'type' => 'physical',
        'unit_price' => 625,
        'quantity' => 2,
        'sub_total' => 1250,
        'total' => 1500,
    ]);

    $data = OrderData::from($this->order->fresh()->load('physicalLines'))->toArray();

    expect($data)
        ->subTotalFormatted->toBe('£12.50')
        ->shippingTotalFormatted->toBe('£4.95')
        ->taxTotalFormatted->toBe('£3.49')
        ->totalFormatted->toBe('£20.94')
        ->and($data['physicalLines'][0])
        ->unitPriceFormatted->toBe('£6.25')
        ->subTotalFormatted->toBe('£12.50')
        ->totalFormatted->toBe('£15.00');
});

test('it shows the product image on each line, as the cart does', function () {
    Storage::fake(config('media-library.disk_name'));
    $product = Product::factory()->for(ProductType::factory())->create();
    $product->addMediaFromString(onePixelPng())
        ->usingFileName('alpha2.png')
        ->withCustomProperties(['primary' => true])
        ->toMediaCollection(config('lunar.media.collection'));
    $variant = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->for($this->order)->create([
        'type' => 'physical',
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
    ]);

    $data = OrderData::from($this->order->fresh()->load('physicalLines'))->toArray();

    expect($data['physicalLines'][0]['thumbnail'])->toContain('alpha2');
});
