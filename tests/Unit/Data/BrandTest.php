<?php

use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Brand;
use Lunar\Storefront\Data\Brand as BrandData;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/** A valid 1x1 PNG — the media collections enforce image mime types and
 *  generate conversions, so the bytes must be a real, loadable image. */
function onePixelPng(): string
{
    $image = imagecreatetruecolor(1, 1);

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

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
