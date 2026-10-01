<?php

use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Brand;
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
