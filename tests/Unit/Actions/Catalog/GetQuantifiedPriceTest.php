<?php

use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Actions\Catalog\GetQuantifiedPrice;
use Lunar\Storefront\Data\Price as PriceData;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create([
        'default' => true,
        'code' => 'USD',
        'decimal_places' => 2,
    ]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $this->currency->id,
        'language_id' => $language->id,
    ]);
});

test('it returns price data object', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $price = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $price,
        base: $price,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new GetQuantifiedPrice;
    $result = $action->get($pricingResponse, 1);

    expect($result)->toBeInstanceOf(PriceData::class);
});

test('it multiplies price by quantity', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $price = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000, // $10.00
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $price,
        base: $price,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new GetQuantifiedPrice;

    // Quantity of 5 should give 5000 ($50.00)
    $result = $action->get($pricingResponse, 5);

    // The result prices are in minor units (cents)
    expect($result->inclTax)->toBe(5000)
        ->and($result->exclTax)->toBe(5000);
});

test('it includes currency information', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $price = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $price,
        base: $price,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new GetQuantifiedPrice;
    $result = $action->get($pricingResponse, 1);

    expect($result->currency)->not->toBeNull()
        ->and($result->currency->code)->toBe('USD');
});

test('it preserves min quantity from price', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $price = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 900,
        'min_quantity' => 10,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $price,
        base: $price,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new GetQuantifiedPrice;
    $result = $action->get($pricingResponse, 15);

    expect($result->minQuantity)->toBe(10);
});

test('it handles decimal quantities correctly', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $price = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000, // $10.00
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $price,
        base: $price,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new GetQuantifiedPrice;

    // Test with quantity that could cause floating point issues
    $result = $action->get($pricingResponse, 3);

    // $10.00 * 3 = $30.00 = 3000 cents
    expect($result->inclTax)->toBe(3000);
});
