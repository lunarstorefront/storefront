<?php

use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Storefront\Data\AttributeDataValue;
use Lunar\Storefront\Data\Traits\HasAttributeData;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it maps model attributes to AttributeDataValue collection', function () {
    $productType = ProductType::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create([
            'attribute_data' => collect([
                'name' => new Text('Test Product'),
                'description' => new Text('A description'),
                'material' => new Text('Cotton'),
            ]),
        ]);

    $testClass = new class
    {
        use HasAttributeData;
    };

    $result = $testClass::mapAttributes($product);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBeInstanceOf(AttributeDataValue::class)
        ->and($result->firstWhere('handle', 'material')->value)->toBe('Cotton');
});

test('it serializes to a JSON array regardless of contents', function () {
    $productType = ProductType::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create([
            'attribute_data' => collect([
                'name' => new Text('Test Product'),
                'material' => new Text('Cotton'),
                'colour' => new Text('Blue'),
            ]),
        ]);

    $testClass = new class
    {
        use HasAttributeData;
    };

    $result = $testClass::mapAttributes($product);

    expect(array_is_list($result->all()))->toBeTrue()
        ->and(json_encode($result))->toStartWith('[');
});

test('it excludes name and description attributes', function () {
    $productType = ProductType::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create([
            'attribute_data' => collect([
                'name' => new Text('Test'),
                'description' => new Text('Description'),
            ]),
        ]);

    $testClass = new class
    {
        use HasAttributeData;
    };

    $result = $testClass::mapAttributes($product);

    expect($result)->toBeEmpty();
});
