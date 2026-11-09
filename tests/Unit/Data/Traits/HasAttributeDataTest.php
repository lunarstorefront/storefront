<?php

use Illuminate\Support\Facades\DB;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Attribute;
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

test('it labels each value with the attribute name, falling back to the handle', function () {
    Attribute::factory()->modelType('product')->create(['handle' => 'release_date', 'name' => 'Release date']);
    Attribute::factory()->modelType('collection')->create(['handle' => 'material', 'name' => 'Not a product attribute']);

    $product = Product::factory()
        ->for(ProductType::factory()->create())
        ->create([
            'attribute_data' => collect([
                'name' => new Text('Test Product'),
                'release_date' => new Text('2011-05-16'),
                'material' => new Text('Cotton'),
                'colour' => new Text('Blue'),
            ]),
        ]);

    $testClass = new class
    {
        use HasAttributeData;
    };

    $names = $testClass::mapAttributes($product)->pluck('name', 'handle')->all();

    expect($names)->toBe([
        'release_date' => 'Release date',
        'material' => 'material',
        'colour' => 'colour',
    ]);
});

test('it looks the attribute names up once for a list of models of one type', function () {
    Attribute::factory()->modelType('product')->create(['handle' => 'release_date', 'name' => 'Release date']);
    $productType = ProductType::factory()->create();
    $products = collect(range(1, 3))->map(fn () => Product::factory()->for($productType)->create([
        'attribute_data' => collect(['name' => new Text('Test Product'), 'release_date' => new Text('2011-05-16')]),
    ]));

    $testClass = new class
    {
        use HasAttributeData;
    };

    DB::enableQueryLog();
    $names = $products->map(fn (Product $product) => $testClass::mapAttributes($product)->first()->name);
    $lookups = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'attributes'));

    expect($names->all())->toBe(['Release date', 'Release date', 'Release date'])
        ->and($lookups)->toHaveCount(1);
});

test('a new request or job sees a renamed attribute', function () {
    $attribute = Attribute::factory()->modelType('product')->create(['handle' => 'release_date', 'name' => 'Release date']);
    $product = Product::factory()->for(ProductType::factory()->create())->create([
        'attribute_data' => collect(['name' => new Text('Test Product'), 'release_date' => new Text('2011-05-16')]),
    ]);

    $testClass = new class
    {
        use HasAttributeData;
    };

    expect($testClass::mapAttributes($product)->first()->name)->toBe('Release date');

    $attribute->update(['name' => 'Released']);
    expect($testClass::mapAttributes($product)->first()->name)->toBe('Release date');

    // What the container does between requests (Octane) and queued jobs.
    app()->forgetScopedInstances();
    expect($testClass::mapAttributes($product)->first()->name)->toBe('Released');
});
