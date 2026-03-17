<?php

use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
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

test('it transforms a price integer to int', function () {
    $transformer = new PriceTransformer();

    $property = Mockery::mock(DataProperty::class);
    $context = Mockery::mock(TransformationContext::class);

    $result = $transformer->transform($property, 1500, $context);

    expect($result)->toBeInt()
        ->and($result)->toBe(1500);
});

test('it includes correct value in transformation', function () {
    $transformer = new PriceTransformer();

    $property = Mockery::mock(DataProperty::class);
    $context = Mockery::mock(TransformationContext::class);

    $result = $transformer->transform($property, 2500, $context);

    expect($result)->toBe(2500);
});
