<?php

use Illuminate\Database\Eloquent\Model;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\DataTypes\ShippingOption as ShippingOptionValue;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\CartLine;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxRate;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Data\Cart as CartData;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create([
        'code' => 'GBP',
        'default' => true,
        'decimal_places' => 2,
        'exchange_rate' => 1,
    ]);
    $this->channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $taxZone = TaxZone::factory()->create(['default' => true]);
    TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);

    Model::preventAccessingMissingAttributes();
});

afterEach(fn () => Model::preventAccessingMissingAttributes(false));

function dtoCartWithLine(int $quantity = 2): Cart
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(test()->taxClass)
        ->create();

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => test()->currency->id,
        'price' => 1000,
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => test()->channel->id,
        'currency_id' => test()->currency->id,
    ]);

    CartLine::factory()->create([
        'cart_id' => $cart->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => $quantity,
    ]);

    return $cart;
}

test('it exposes the calculated shipping breakdown', function () {
    $cart = dtoCartWithLine();
    $cart->shippingOptionOverride = new ShippingOptionValue(
        name: 'Standard delivery',
        description: 'Standard delivery',
        identifier: 'STANDARD',
        price: new PriceValue(695, test()->currency),
        taxClass: test()->taxClass,
    );

    $data = CartData::from($cart->calculate());

    expect($data->shippingBreakdown)->not->toBeNull()
        ->and($data->shippingBreakdown->items)->toHaveCount(1)
        ->and($data->shippingBreakdown->items->first()->identifier)->toBe('STANDARD');
});

test('it reads missing line aggregates as null under strict attribute access', function () {
    $cart = Cart::find(dtoCartWithLine()->id);

    $data = CartData::from($cart->calculate());

    expect($data->linesCount)->toBeNull()
        ->and($data->totalQuantity)->toBe(2);
});

test('it reports line aggregates when the query loaded them', function () {
    $cart = Cart::withCount('lines')->withSum('lines', 'quantity')->find(dtoCartWithLine(3)->id);

    $data = CartData::from($cart->calculate());

    expect($data->linesCount)->toBe(1)
        ->and($data->totalQuantity)->toBe(3);
});

test('it formats the discount total', function () {
    $data = CartData::from(dtoCartWithLine()->calculate());

    expect($data->discountTotalFormatted)->toBeString()
        ->and($data->discountTotalFormatted)->toContain('0.00')
        ->and($data->toArray())->toHaveKey('discountTotalFormatted');
});
