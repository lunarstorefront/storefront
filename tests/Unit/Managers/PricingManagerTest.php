<?php

use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Storefront\Contracts\PricingManager as PricingManagerContract;
use Lunar\Storefront\Data\Price as DataPrice;
use Lunar\Storefront\Managers\PricingManager;

beforeEach(function () {
    $this->manager = new PricingManager();

    Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it implements the pricing manager contract', function () {
    expect($this->manager)->toBeInstanceOf(PricingManagerContract::class);
});

test('it returns null when pricing cannot be resolved', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);

    $product = Product::factory()
        ->for($productType)
        ->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    // No price and no StorefrontSession currency set
    $pricing = $this->manager->getPricing($variant);

    expect($pricing)->toBeNull();
});

test('it can map price breaks from pricing response', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);

    $product = Product::factory()
        ->for($productType)
        ->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $matchedPrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1200,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $matchedPrice,
        base: $basePrice,
        priceBreaks: collect([$matchedPrice]),
        customerGroupPrices: collect([]),
    );

    $priceBreaks = $this->manager->mapPriceBreaks($pricingResponse);

    expect($priceBreaks)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('it can get quantified price from pricing response', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);

    $product = Product::factory()
        ->for($productType)
        ->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $matchedPrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1200,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $matchedPrice,
        base: $basePrice,
        priceBreaks: collect([$matchedPrice]),
        customerGroupPrices: collect([]),
    );

    $quantifiedPrice = $this->manager->getQuantifiedPrice($pricingResponse, 5);

    expect($quantifiedPrice)->toBeInstanceOf(DataPrice::class);
});
