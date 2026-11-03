<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;

beforeEach(function () {
    config(['storefront.install.requires' => []]);
    Http::fake(['data.lunarphp.io/*' => Http::response([
        ['name' => 'United Kingdom', 'iso3' => 'GBR', 'iso2' => 'GB', 'phone_code' => '44', 'currency' => 'GBP', 'emoji' => '', 'emojiU' => '', 'capital' => 'London', 'native' => 'United Kingdom', 'states' => []],
    ])]);
});

it('installs the uk preset', function () {
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])->assertSuccessful();
    expect(Currency::getDefault()->code)->toBe('GBP');
});

it('does not fetch countries when they already exist', function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])->assertSuccessful();
    Http::assertNothingSent();
});

it('fails clearly when the country import returns nothing', function () {
    Http::swap(new Factory);
    Http::fake(['data.lunarphp.io/*' => Http::response([], 500)]);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Country import failed')->assertFailed();
});

it('fails clearly when the country import throws', function () {
    Http::swap(new Factory);
    Http::fake(['data.lunarphp.io/*' => fn () => throw new RuntimeException('boom')]);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Country import failed')->assertFailed();
});

it('fails before changing anything when a required package is missing', function () {
    config(['storefront.install.requires' => ['acme/missing' => 'Acme\\Missing\\Provider']]);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Missing required packages: acme/missing')->assertFailed();
    expect(Country::count())->toBe(0);
});

it('rejects an unknown preset', function () {
    $this->artisan('storefront:install', ['--preset' => 'mars', '--skip-search' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Unknown preset "mars". Available: uk')->assertFailed();
});
