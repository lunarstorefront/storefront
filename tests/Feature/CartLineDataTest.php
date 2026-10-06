<?php

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
use Lunar\Core\Models\TaxRateAmount;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Data\CartLine as CartLineData;

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
    $this->taxZone = TaxZone::factory()->create(['default' => true]);
    $this->taxRate = TaxRate::factory()->create(['tax_zone_id' => $this->taxZone->id]);
});

/** A calculated cart line for a variant priced at 1000 (ex tax) in the given tax class. */
function calculatedLine(TaxClass $taxClass, int $quantity = 1): CartLine
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for($taxClass)
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

    return $cart->calculate()->lines->first();
}

test('it carries the unit price inclusive of the tax rate of the line', function (int $percentage, int $inclTax, string $formatted) {
    $taxClass = TaxClass::factory()->create(['default' => true]);
    TaxRateAmount::factory()->create([
        'tax_rate_id' => $this->taxRate->id,
        'tax_class_id' => $taxClass->id,
        'percentage' => $percentage,
    ]);

    $data = CartLineData::fromModel(calculatedLine($taxClass, quantity: 3));

    expect($data)
        ->unitPrice->toBe(1000)
        ->unitPriceInclTax->toBe($inclTax)
        ->unitPriceFormattedInclTax->toBe($formatted);
})->with([
    'standard rate' => [20, 1200, '£12.00'],
    'reduced rate' => [5, 1050, '£10.50'],
    'zero rate' => [0, 1000, '£10.00'],
]);
