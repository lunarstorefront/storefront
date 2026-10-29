<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxRate;
use Lunar\Core\Models\TaxRateAmount;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Actions\Catalog\MapProductPriceBreaks;
use Lunar\Storefront\Data\Price as PriceData;
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

test('it resolves each tier inclusive of the tax rate of the variant', function (int $percentage, array $inclTax) {
    $taxClass = TaxClass::factory()->create(['default' => true]);
    TaxRateAmount::factory()->create([
        'tax_rate_id' => TaxRate::factory()->create([
            'tax_zone_id' => TaxZone::factory()->create(['default' => true])->id,
        ])->id,
        'tax_class_id' => $taxClass->id,
        'percentage' => $percentage,
    ]);

    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for($taxClass)
        ->create();

    $price = fn (int $amount, int $minQuantity, ?int $listPrice = null) => Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => $amount,
        'list_price' => $listPrice,
        'min_quantity' => $minQuantity,
    ]);

    $basePrice = $price(1000, 1, 1500);

    $breaks = (new MapProductPriceBreaks)->map(new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$price(900, 10), $price(800, 50)]),
        customerGroupPrices: collect([]),
    ));

    expect($breaks->map(fn (PriceBreak $break) => $break->price->exclTax)->all())->toBe([1000, 900, 800])
        ->and($breaks->map(fn (PriceBreak $break) => $break->price->inclTax)->all())->toBe($inclTax)
        ->and($breaks->first()->price->comparePriceExcTax)->toBe(1500)
        ->and($breaks->first()->price->comparePriceIncTax)->toBe((int) round(1500 * (1 + $percentage / 100)))
        ->and($breaks->first()->price->formattedComparePriceExcTax)->not->toBeNull();
})->with([
    'standard rate' => [20, [1200, 1080, 960]],
    'reduced rate' => [5, [1050, 945, 840]],
    'zero rate' => [0, [1000, 900, 800]],
]);

test('it strips tax from each tier when prices are stored inclusive of tax', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $taxClass = TaxClass::factory()->create(['default' => true]);
    TaxRateAmount::factory()->create([
        'tax_rate_id' => TaxRate::factory()->create([
            'tax_zone_id' => TaxZone::factory()->create(['default' => true])->id,
        ])->id,
        'tax_class_id' => $taxClass->id,
        'percentage' => 20,
    ]);

    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for($taxClass)
        ->create();

    $price = fn (int $amount, int $minQuantity, ?int $listPrice = null) => Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => $amount,
        'list_price' => $listPrice,
        'min_quantity' => $minQuantity,
    ]);

    $basePrice = $price(1200, 1, 1800);

    $breaks = (new MapProductPriceBreaks)->map(new PricingResponse(
        matched: $basePrice,
        base: $basePrice,
        priceBreaks: collect([$price(1080, 10), $price(960, 50)]),
        customerGroupPrices: collect([]),
    ));

    $base = $breaks->first()->price;

    expect($breaks->map(fn (PriceBreak $break) => $break->price->exclTax)->all())->toBe([1000, 900, 800])
        ->and($breaks->map(fn (PriceBreak $break) => $break->price->inclTax)->all())->toBe([1200, 1080, 960])
        ->and($base->comparePriceExcTax)->toBe(1500)
        ->and($base->comparePriceIncTax)->toBe(1800)
        ->and($base->formattedComparePriceExcTax)->toBe((new PriceValue(1500, $this->currency))->format())
        ->and($basePrice->price)->toBe(1200);
});

test('it treats a list price of zero as no compare price', function () {
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(TaxClass::factory()->create(['default' => true]))
        ->create();

    $basePrice = Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $this->currency->id,
        'price' => 1000,
        'list_price' => 0,
        'min_quantity' => 1,
    ]);

    $price = PriceData::fromModel($basePrice);

    expect($price->hasComparePrice)->toBeFalse()
        ->and($price->comparePriceExcTax)->toBeNull()
        ->and($price->comparePriceIncTax)->toBeNull();
});

/**
 * @param  array<int, array{0: int, 1: int, 2?: int|null}>  $rows  [price, min_quantity, customer_group_id]
 * @return array{0: Price, 1: Collection<int, Price>}
 */
function priceBreakFixtureRows(int $basePrice, array $rows): array
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(TaxClass::factory()->create(['default' => true]))
        ->create();

    $price = fn (int $amount, int $minQuantity, ?int $customerGroupId = null) => Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => Currency::getDefault()->id,
        'customer_group_id' => $customerGroupId,
        'price' => $amount,
        'min_quantity' => $minQuantity,
    ]);

    return [$price($basePrice, 1), collect($rows)->map(fn (array $row) => $price(...$row))];
}

function mapPriceBreakFixture(Price $base, Collection $breaks, int $min = 1): Collection
{
    return (new MapProductPriceBreaks)->map(new PricingResponse(
        matched: $base,
        base: $base,
        priceBreaks: $breaks,
        customerGroupPrices: collect(),
    ), $min);
}

test('it shows one tier per quantity at the cheapest of the customer groups prices', function () {
    $trade = CustomerGroup::factory()->create();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [900, 10],
        [850, 10, $trade->id],
        [800, 50],
    ]);

    $result = mapPriceBreakFixture($base, $breaks);

    expect($result->map(fn (PriceBreak $break) => [$break->lowerLimit, $break->upperLimit, $break->price->exclTax])->all())
        ->toBe([[1, 9, 1000], [10, 49, 850], [50, null, 800]]);
});

test('it leaves out and logs a tier that does not lower the price', function () {
    Log::spy();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [900, 10],
        [950, 25],
        [800, 50],
    ]);

    $result = mapPriceBreakFixture($base, $breaks);

    expect($result->map(fn (PriceBreak $break) => [$break->lowerLimit, $break->upperLimit, $break->price->exclTax])->all())
        ->toBe([[1, 9, 1000], [10, 49, 900], [50, null, 800]]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => ($context['min_quantity'] ?? null) === 25)
        ->once();
});

test('it logs two rows for one customer group at the same quantity', function () {
    Log::spy();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [900, 10],
        [880, 10],
    ]);

    $result = mapPriceBreakFixture($base, $breaks);

    expect($result->map(fn (PriceBreak $break) => [$break->lowerLimit, $break->upperLimit, $break->price->exclTax])->all())
        ->toBe([[1, 9, 1000], [10, null, 880]]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => ($context['min_quantity'] ?? null) === 10)
        ->once();
});

test('it keeps the cheapest row for the starting tier when breaks fall at or below the minimum', function () {
    Log::spy();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [900, 3],
        [950, 4],
        [800, 10],
    ]);

    $result = mapPriceBreakFixture($base, $breaks, min: 5);

    expect($result->map(fn (PriceBreak $break) => [$break->lowerLimit, $break->upperLimit, $break->price->exclTax])->all())
        ->toBe([[5, 9, 900], [10, null, 800]]);

    // Breaks below the minimum are never shown on their own, so a pricier
    // one there is not worth a warning.
    Log::shouldNotHaveReceived('warning');
});

test('it does not let a break at the minimum replace a cheaper base price', function () {
    Log::spy();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [1100, 5],
        [900, 10],
    ]);

    $result = mapPriceBreakFixture($base, $breaks, min: 5);

    expect($result->map(fn (PriceBreak $break) => [$break->lowerLimit, $break->upperLimit, $break->price->exclTax])->all())
        ->toBe([[5, 9, 1000], [10, null, 900]]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => ($context['min_quantity'] ?? null) === 5)
        ->once();
});

test('it reports a bad break once a day rather than on every render', function () {
    Log::spy();

    [$base, $breaks] = priceBreakFixtureRows(1000, [
        [1100, 10],
    ]);

    mapPriceBreakFixture($base, $breaks);
    mapPriceBreakFixture($base, $breaks);

    Log::shouldHaveReceived('warning')->once();

    $this->travel(25)->hours();
    mapPriceBreakFixture($base, $breaks);

    Log::shouldHaveReceived('warning')->twice();
});
