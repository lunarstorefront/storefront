<?php

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Storefront\Data\Transformers\PriceTransformer;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it transforms a price object to array', function () {
    $priceDataType = new \Lunar\DataTypes\Price(1500, $this->currency, 1);

    $transformer = new PriceTransformer();

    $property = Mockery::mock(DataProperty::class);
    $context = Mockery::mock(TransformationContext::class);

    $result = $transformer->transform($property, $priceDataType, $context);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['decimal', 'decimal_unit', 'formatted', 'formatted_unit', 'value', 'unit_qty']);
});

test('it includes correct value in transformation', function () {
    $priceDataType = new \Lunar\DataTypes\Price(2500, $this->currency, 1);

    $transformer = new PriceTransformer();

    $property = Mockery::mock(DataProperty::class);
    $context = Mockery::mock(TransformationContext::class);

    $result = $transformer->transform($property, $priceDataType, $context);

    expect($result['value'])->toBe(2500);
});
