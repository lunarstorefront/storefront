<?php

use Lunar\Core\Actions\Taxes\GetTaxZoneCountry;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxRate;
use Lunar\Core\Models\TaxZone;
use Lunar\Core\Models\TaxZoneCountry;
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
    $old = TaxZone::factory()->create(['name' => 'Default Tax Zone', 'default' => true, 'active' => true]);
    $gb = Country::where('iso2', 'GB')->first();
    $fr = Country::where('iso2', 'FR')->first();
    $old->countries()->create(['country_id' => $gb->id]);
    $old->countries()->create(['country_id' => $fr->id]);

    (new SeedStoreSettings)();

    $uk = TaxZone::where('name', 'UK')->first();
    expect(TaxZoneCountry::where('country_id', $gb->id)->pluck('tax_zone_id')->all())->toBe([$uk->id])
        ->and($old->countries()->pluck('country_id')->all())->toBe([$fr->id])
        ->and((new GetTaxZoneCountry)->execute($gb->id)->tax_zone_id)->toBe($uk->id);

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

it('leaves staff-removed child rows alone on a re-run', function () {
    (new SeedStoreSettings)();

    $row = TaxZone::where('name', 'Rest of World')->first();
    $row->countries()->delete();
    $uk = TaxZone::where('name', 'UK')->first();
    $uk->taxAmounts()->first()->delete();
    $amountsBefore = $uk->taxAmounts()->count();

    (new SeedStoreSettings)();

    expect($row->countries()->count())->toBe(0)
        ->and($uk->taxAmounts()->count())->toBe($amountsBefore);
});

it('does not move GB back from a zone staff attached it to on a re-run', function () {
    (new SeedStoreSettings)();
    $gb = Country::where('iso2', 'GB')->first();
    $other = TaxZone::factory()->create(['name' => 'Custom', 'active' => true]);
    $other->countries()->create(['country_id' => $gb->id]);

    (new SeedStoreSettings)();

    expect($other->countries()->where('country_id', $gb->id)->exists())->toBeTrue();
});

it('falls back to the seeded rows when no defaults exist for the region', function () {
    Channel::query()->delete();
    Currency::query()->delete();
    Language::query()->delete();

    (new SeedStoreSettings)();

    $region = Region::where('handle', 'united-kingdom')->sole()->load('channel', 'currency', 'language');
    expect($region->channel->handle)->toBe('webstore')
        ->and($region->currency->code)->toBe('GBP')
        ->and($region->language->code)->toBe('en');
});
