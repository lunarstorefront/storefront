<?php

use Lunar\Core\Models\Address;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\CartAddress as CartAddressModel;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress as OrderAddressModel;
use Lunar\Storefront\Data\Address as AddressData;
use Lunar\Storefront\Data\CartAddress as CartAddressData;
use Lunar\Storefront\Data\OrderAddress as OrderAddressData;

beforeEach(function () {
    $this->country = Country::factory()->create();
});

test('the address DTO identifies an address by its public id, not its row id', function () {
    $address = Address::factory()->create([
        'country_id' => $this->country->id,
        'shipping_default' => false,
        'billing_default' => false,
    ]);

    $data = AddressData::fromModel($address);

    expect($data->id)->toBe($address->public_id)
        ->and($data->id)->not->toBe((string) $address->id);
});

test('the order address DTO identifies an address by its public id', function () {
    $address = OrderAddressModel::factory()->for(Order::factory())->create(['country_id' => $this->country->id]);

    expect(OrderAddressData::fromModel($address)->id)->toBe($address->public_id);
});

test('the cart address DTO identifies an address by its public id', function () {
    $address = CartAddressModel::factory()->for(Cart::factory())->create(['country_id' => $this->country->id]);

    expect(CartAddressData::fromModel($address)->id)->toBe($address->public_id);
});
