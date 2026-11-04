<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Storefront\Data\Brand as BrandData;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

test('it resolves the logo from the dedicated logo media collection', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    $brand->addMediaFromString(onePixelPng())->usingFileName('logo.png')->toMediaCollection('logo');

    $data = BrandData::from($brand->load('media'))->toArray();

    expect($data['logo'])->not->toBeNull()
        ->and($data['logo']['collectionName'])->toBe('logo')
        ->and($data['logo']['original'])->toContain('logo.png');
});

test('it falls back to the primary image when there is no logo media', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    $brand->addMediaFromString(onePixelPng())
        ->usingFileName('primary.png')
        ->withCustomProperties(['primary' => true])
        ->toMediaCollection(config('lunar.media.collection'));

    $data = BrandData::from($brand->load('media'))->toArray();

    expect($data['logo'])->not->toBeNull()
        ->and($data['logo']['original'])->toContain('primary.png');
});

test('it exposes a null logo when the brand has no logo or primary image', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);

    $data = BrandData::from($brand->load('media'))->toArray();

    expect($data['logo'])->toBeNull();
});

test('it reads a missing products_count as zero under strict attribute access', function () {
    Model::preventAccessingMissingAttributes();

    $brand = Brand::factory()->create(['name' => 'Acme']);

    expect(BrandData::from(Brand::find($brand->id))->productsCount)->toBe(0);
});

test('it reports products_count when the query aggregated it', function () {
    Model::preventAccessingMissingAttributes();

    $brand = Brand::factory()->create(['name' => 'Acme']);
    Product::factory()->for(ProductType::factory())->create(['brand_id' => $brand->id]);

    $counted = Brand::withCount('products')->find($brand->id);

    expect(BrandData::from($counted)->productsCount)->toBe(1);
});

afterEach(fn () => Model::preventAccessingMissingAttributes(false));
