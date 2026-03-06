<?php

use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Storefront\Contracts\BrandManager as BrandManagerContract;
use Lunar\Storefront\Managers\BrandManager;

beforeEach(function () {
    $this->manager = new BrandManager();

    $this->language = Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it implements the brand manager contract', function () {
    expect($this->manager)->toBeInstanceOf(BrandManagerContract::class);
});

test('it can get brand by slug', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);

    $brand->urls()->create([
        'slug' => 'acme',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $found = $this->manager->getBySlug('acme');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($brand->id);
});

test('it throws when brand slug not found', function () {
    $this->manager->getBySlug('nonexistent');
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('it returns paginated brands with default urls', function () {
    $brandA = Brand::factory()->create(['name' => 'Acme']);
    $brandA->urls()->create([
        'slug' => 'acme',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $brandB = Brand::factory()->create(['name' => 'Beta']);
    $brandB->urls()->create([
        'slug' => 'beta',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    // Brand without a URL should be excluded.
    $ghost = Brand::factory()->create(['name' => 'Ghost']);
    $ghost->urls()->delete();

    $result = $this->manager->getPaginated();

    expect($result)->toBeInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
        ->and($result->total())->toBe(2);
});

test('it returns paginated brands ordered by name', function () {
    $brandB = Brand::factory()->create(['name' => 'Zebra']);
    $brandB->urls()->create([
        'slug' => 'zebra',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $brandA = Brand::factory()->create(['name' => 'Alpha']);
    $brandA->urls()->create([
        'slug' => 'alpha',
        'default' => true,
        'language_id' => $this->language->id,
    ]);

    $result = $this->manager->getPaginated();

    expect($result->first()->name)->toBe('Alpha')
        ->and($result->last()->name)->toBe('Zebra');
});

test('it respects perPage parameter', function () {
    for ($i = 0; $i < 5; $i++) {
        $brand = Brand::factory()->create(['name' => "Brand {$i}"]);
        $brand->urls()->create([
            'slug' => "brand-{$i}",
            'default' => true,
            'language_id' => $this->language->id,
        ]);
    }

    $result = $this->manager->getPaginated(2);

    expect($result->perPage())->toBe(2)
        ->and($result->total())->toBe(5);
});
