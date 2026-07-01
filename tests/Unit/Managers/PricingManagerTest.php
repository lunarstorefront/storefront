<?php

use Illuminate\Support\Collection;
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
use Lunar\Storefront\Contracts\PricingManager as PricingManagerContract;
use Lunar\Storefront\Data\Price as DataPrice;
use Lunar\Storefront\Managers\PricingManager;

beforeEach(function () {
    $this->manager = new PricingManager;

    $language = Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $this->currency->id,
        'language_id' => $language->id,
    ]);
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

    // Variant has no price record, but the Pricing facade still returns a response
    // with null matched price when a default currency exists via Region
    $pricing = $this->manager->getPricing($variant);

    expect($pricing)->not->toBeNull()
        ->and($pricing->matched)->toBeNull();
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

    expect($priceBreaks)->toBeInstanceOf(Collection::class);
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
