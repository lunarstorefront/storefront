<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Storefront\Actions\Catalog\GetProductBySlug;
use Lunar\Storefront\Data\Product as ProductData;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns product data by default', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'published']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();
    $result = $action->get('test-product');

    expect($result)->toBeInstanceOf(ProductData::class);
});

test('it returns model when asModel is true', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'published']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();
    $result = $action->get('test-product', asModel: true);

    expect($result)->toBeInstanceOf(Product::class)
        ->and($result->id)->toBe($product->id);
});

test('it throws exception for non-existent product', function () {
    $action = new GetProductBySlug();
    $action->get('non-existent');
})->throws(ModelNotFoundException::class);

test('it only finds published products', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'draft']);

    $product->urls()->create([
        'slug' => 'draft-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();
    $action->get('draft-product');
})->throws(ModelNotFoundException::class);

test('it only matches default urls', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'published']);

    $product->urls()->create([
        'slug' => 'non-default',
        'default' => false,
        'language_id' => $language->id,
    ]);

    $product->urls()->create([
        'slug' => 'default-url',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();

    // Non-default URL should not be found
    expect(fn () => $action->get('non-default'))->toThrow(ModelNotFoundException::class);

    // Default URL should be found
    $result = $action->get('default-url', asModel: true);
    expect($result->id)->toBe($product->id);
});

test('it eager loads product type with mapped attributes', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'published']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();
    $result = $action->get('test-product', asModel: true);

    expect($result->relationLoaded('productType'))->toBeTrue()
        ->and($result->productType->relationLoaded('mappedAttributes'))->toBeTrue();
});

test('it eager loads images and thumbnail', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'published']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug();
    $result = $action->get('test-product', asModel: true);

    expect($result->relationLoaded('images'))->toBeTrue()
        ->and($result->relationLoaded('thumbnail'))->toBeTrue();
});
