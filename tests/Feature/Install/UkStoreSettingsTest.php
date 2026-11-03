<?php

use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxRate;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Install\Presets\Uk\SeedStoreSettings;

beforeEach(fn () => Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom'])
    && Country::factory()->create(['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France']));

it('seeds GBP as the only default currency', function () {
    (new SeedStoreSettings)();
    expect(Currency::where('default', true)->pluck('code')->all())->toBe(['GBP']);
});

it('seeds UK VAT rates', function () {
    (new SeedStoreSettings)();
    $zone = TaxZone::where('name', 'UK')->first()->load('taxAmounts.taxClass');
    $amounts = $zone->taxAmounts->mapWithKeys(fn ($a) => [$a->taxClass->name => (float) $a->percentage]);
    expect($amounts->all())->toEqual(['Standard' => 20.0, 'Reduced' => 5.0, 'Zero' => 0.0]);
});

it('links the default region to the UK tax zone and defaults', function () {
    (new SeedStoreSettings)();
    $region = Region::where('default', true)->sole()->load('taxZone', 'currency');
    expect($region->handle)->toBe('united-kingdom')
        ->and($region->taxZone->name)->toBe('UK')
        ->and($region->currency->code)->toBe('GBP');
});

it('puts every non-GB country in the rest of world zone', function () {
    (new SeedStoreSettings)();
    $zone = TaxZone::where('name', 'Rest of World')->first()->load('countries.country');
    expect($zone->countries->pluck('country.iso2')->all())->toBe(['FR']);
});

it('creates retail and trade groups with retail as default', function () {
    (new SeedStoreSettings)();
    expect(CustomerGroup::where('default', true)->sole()->handle)->toBe('retail')
        ->and(CustomerGroup::where('handle', 'trade')->exists())->toBeTrue();
});

it('gives the general product type the name and description attributes', function () {
    (new SeedStoreSettings)();
    expect(ProductType::where('handle', 'general')->sole()->productAttributes->pluck('handle')->sort()->values()->all())
        ->toBe(['description', 'name']);
});

it('replaces lunar install defaults without duplicating them', function () {
    Currency::factory()->create(['code' => 'USD', 'default' => true]);
    Channel::factory()->create(['handle' => 'webstore', 'default' => true]);
    TaxZone::factory()->create(['name' => 'Default Tax Zone', 'default' => true]);

    (new SeedStoreSettings)();

    expect(Currency::where('default', true)->count())->toBe(1)
        ->and(Channel::where('handle', 'webstore')->count())->toBe(1)
        ->and(TaxZone::where('default', true)->sole()->name)->toBe('UK');
});

it('changes nothing on a second run', function () {
    (new SeedStoreSettings)();
    CustomerGroup::where('handle', 'trade')->update(['name' => 'Trade customers']);
    $counts = fn () => [Currency::count(), TaxZone::count(), TaxRate::count(), Region::count(), ProductType::count(), Attribute::count()];
    $before = $counts();

    (new SeedStoreSettings)();

    expect($counts())->toBe($before)
        ->and(CustomerGroup::where('handle', 'trade')->value('name'))->toBe('Trade customers');
});

it('refuses to run without countries', function () {
    Country::query()->delete();
    (new SeedStoreSettings)();
})->throws(RuntimeException::class, 'Import countries before seeding the uk preset');
