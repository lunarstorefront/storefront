<?php

use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Catalog\Models\Price;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductType;
use Lunar\Catalog\Models\ProductVariant;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Actions\Catalog\MapProductPriceBreaks;
use Lunar\Storefront\Data\PriceBreak;

beforeEach(function () {
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

test('it returns empty collection when no price breaks', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([]),
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse);

    expect($result)->toBeEmpty();
});

test('it maps single price break with base price', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $tierPrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 900,
        'min_quantity' => 10,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$tierPrice]),
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse);

    // Should have base tier (1-9) and price break tier (10+)
    expect($result)->toHaveCount(2);

    $baseTier = $result->first();
    expect($baseTier)->toBeInstanceOf(PriceBreak::class)
        ->and($baseTier->lowerLimit)->toBe(1)
        ->and($baseTier->upperLimit)->toBe(9);

    $breakTier = $result->last();
    expect($breakTier->lowerLimit)->toBe(10)
        ->and($breakTier->upperLimit)->toBeNull();
});

test('it maps multiple price breaks in correct order', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $tier10 = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 900,
        'min_quantity' => 10,
    ]);

    $tier50 = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 800,
        'min_quantity' => 50,
    ]);

    $tier100 = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 700,
        'min_quantity' => 100,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$tier50, $tier10, $tier100]), // Deliberately out of order
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse);

    // Should have 4 tiers: base (1-9), 10-49, 50-99, 100+
    expect($result)->toHaveCount(4);

    // Check the boundaries
    expect($result[0]->lowerLimit)->toBe(1)
        ->and($result[0]->upperLimit)->toBe(9);

    expect($result[1]->lowerLimit)->toBe(10)
        ->and($result[1]->upperLimit)->toBe(49);

    expect($result[2]->lowerLimit)->toBe(50)
        ->and($result[2]->upperLimit)->toBe(99);

    expect($result[3]->lowerLimit)->toBe(100)
        ->and($result[3]->upperLimit)->toBeNull();
});

test('it uses custom min value for base tier', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    $tierPrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 900,
        'min_quantity' => 10,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$tierPrice]),
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse, min: 5);

    // Base tier should start at 5, not 1
    expect($result->first()->lowerLimit)->toBe(5)
        ->and($result->first()->upperLimit)->toBe(9);
});

test('it does not duplicate base tier when first price break starts at min quantity', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 4550,
        'min_quantity' => 1,
    ]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$basePrice]),
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse);

    expect($result)->toHaveCount(1)
        ->and($result[0]->lowerLimit)->toBe(1)
        ->and($result[0]->upperLimit)->toBeNull();
});

/**
 * POTENTIAL BUG: sortBy() preserves original keys, so $priceBreaks[$index + 1]
 * may not reference the next item in sorted order
 */
test('it handles non-sequential keys after sorting', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create(['default' => true]);
    $product = Product::factory()->for($productType)->create();

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'min_quantity' => 1,
    ]);

    // Create prices with specific IDs that will have non-sequential keys when sorted
    $tier100 = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 700,
        'min_quantity' => 100,
    ]);

    $tier10 = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 900,
        'min_quantity' => 10,
    ]);

    // The collection has keys [0 => tier100, 1 => tier10]
    // After sortBy('min_quantity'), keys are preserved: [1 => tier10, 0 => tier100]
    // So $priceBreaks[0] is tier100, not tier10!
    $priceBreaks = collect([$tier100, $tier10]);

    $pricingResponse = new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: $priceBreaks,
        customerGroupPrices: collect([]),
    );

    $action = new MapProductPriceBreaks;
    $result = $action->map($pricingResponse);

    // This test verifies the boundaries are correct despite key ordering issues
    // If there's a bug, the upper limits will be wrong
    expect($result)->toHaveCount(3);

    // Base tier: 1-9
    expect($result[0]->lowerLimit)->toBe(1)
        ->and($result[0]->upperLimit)->toBe(9);

    // Tier 10: 10-99
    expect($result[1]->lowerLimit)->toBe(10)
        ->and($result[1]->upperLimit)->toBe(99);

    // Tier 100: 100+
    expect($result[2]->lowerLimit)->toBe(100)
        ->and($result[2]->upperLimit)->toBeNull();
});
