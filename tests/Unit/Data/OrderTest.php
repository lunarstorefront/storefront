<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress as OrderAddressModel;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\States\Order\Payment\Paid;
use Lunar\Storefront\Data\Order as OrderData;
use Lunar\Storefront\Data\OrderAddress;

beforeEach(function () {
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
