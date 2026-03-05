<?php

use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Storefront\Managers\BrandManager;

beforeEach(function () {
    $this->manager = new BrandManager();

    $this->language = Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns paginated brands with default urls', function () {
    // The URL generator auto-creates a default URL for brands with a name,
    // so both brands will have URLs and should appear in results.
    Brand::factory()->create(['name' => 'Acme']);
    Brand::factory()->create(['name' => 'Beta']);

    // Create a brand without a URL by deleting the auto-generated one.
    $brandWithoutUrl = Brand::factory()->create(['name' => 'Ghost']);
    $brandWithoutUrl->urls()->delete();

    $props = $this->manager->getIndexPageProps();

    expect($props)->toHaveKey('brands')
        ->and($props['brands'])->toHaveCount(2);
});

test('it throws when brand slug not found', function () {
    $this->manager->getShowPageProps('nonexistent', null, 40);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
