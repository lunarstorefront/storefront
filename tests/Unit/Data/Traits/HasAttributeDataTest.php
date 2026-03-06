<?php

use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
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

    $attributeGroup = AttributeGroup::factory()->create([
        'attributable_type' => Product::class,
    ]);

    $attribute = Attribute::factory()->create([
        'attribute_type' => Product::class,
        'attribute_group_id' => $attributeGroup->id,
        'handle' => 'material',
        'name' => collect(['en' => 'Material']),
    ]);

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
        ->and($result->has('material'))->toBeTrue()
        ->and($result->get('material'))->toBeInstanceOf(AttributeDataValue::class)
        ->and($result->get('material')->value)->toBe('Cotton');
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
