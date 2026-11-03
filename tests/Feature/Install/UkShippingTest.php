<?php

use Lunar\Core\Models\Country;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Shipping\DataTransferObjects\PostcodeLookup;
use Lunar\Shipping\Facades\Shipping;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Storefront\Install\Presets\UkPreset;

beforeEach(function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'United Kingdom']);
    (new UkPreset)->seed();
    $this->zonesFor = fn (string $postcode) => Shipping::zones()
        ->country($gb = Country::where('iso2', 'GB')->sole())
        ->postcode(new PostcodeLookup($gb, $postcode))
        ->get()->pluck('name')->sort()->values()->all();
});

it('resolves zones for real-world postcodes', function (string $postcode, array $zones) {
    expect(($this->zonesFor)($postcode))->toBe($zones);
})->with([
    'mainland area' => ['AB10 1AA', ['Mainland', 'United Kingdom']],
    'lowercase mainland' => ['ab10 1aa', ['Mainland', 'United Kingdom']],
    'highland district' => ['PA20 9ZZ', ['Highlands and Islands', 'United Kingdom']],
    'neighbouring mainland district' => ['PA2 1AA', ['Mainland', 'United Kingdom']],
    'island area lowercase' => ['hs1 2ab', ['Highlands and Islands', 'United Kingdom']],
    'northern ireland' => ['bt1 1aa', ['Excluded', 'United Kingdom']],
    'birmingham not belfast' => ['B1 1AA', ['Mainland', 'United Kingdom']],
]);

it('prices standard and next day by zone', function () {
    $price = fn (string $method, string $zone) => ShippingRate::query()
        ->whereRelation('shippingMethod', 'code', $method)->whereRelation('shippingZone', 'name', $zone)
        ->sole()->prices()->sole()->price;

    expect($price('standard', 'Mainland'))->toBe(695)
        ->and($price('standard', 'Highlands and Islands'))->toBe(2495)
        ->and($price('next-day', 'Mainland'))->toBe(1295);
});

it('offers every method to every customer group', function () {
    ShippingMethod::all()->each(fn ($method) => expect($method->customerGroups()->wherePivot('enabled', true)->count())
        ->toBe(CustomerGroup::count()));
});

it('keeps staff price changes on a second run', function () {
    $rate = ShippingRate::whereRelation('shippingMethod', 'code', 'standard')->whereRelation('shippingZone', 'name', 'Mainland')->sole();
    $rate->prices()->sole()->update(['price' => 795]);

    (new UkPreset)->seed();

    expect($rate->prices()->count())->toBe(1)->and($rate->prices()->sole()->price)->toBe(795)
        ->and(ShippingZone::count())->toBe(4)->and(ShippingMethod::count())->toBe(3);
});
