<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductType;
use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
use Lunar\Kernel\Models\Region;
use Lunar\Storefront\Actions\Catalog\GetProductBySlug;
use Lunar\Storefront\Data\Product as ProductData;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);
});

test('it returns product data by default', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'active']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug;
    $result = $action->get('test-product');

    expect($result)->toBeInstanceOf(ProductData::class);
});

test('it returns model when asModel is true', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'active']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug;
    $result = $action->get('test-product', asModel: true);

    expect($result)->toBeInstanceOf(Product::class)
        ->and($result->id)->toBe($product->id);
});

test('it throws exception for non-existent product', function () {
    $action = new GetProductBySlug;
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

    $action = new GetProductBySlug;
    $action->get('draft-product');
})->throws(ModelNotFoundException::class);

test('it only matches default urls', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'active']);

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

    $action = new GetProductBySlug;

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
        ->create(['status' => 'active']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug;
    $result = $action->get('test-product', asModel: true);

    expect($result->relationLoaded('productType'))->toBeTrue()
        ->and($result->productType->relationLoaded('productBlueprint'))->toBeTrue();
});

test('it eager loads images and thumbnail', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'active']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetProductBySlug;
    $result = $action->get('test-product', asModel: true);

    expect($result->relationLoaded('media'))->toBeTrue()
        ->and($result->relationLoaded('thumbnail'))->toBeTrue();
});
