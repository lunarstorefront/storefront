<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Staff;
use Lunar\Core\Models\TaxRate;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;

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

it('creates the admin from options', function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true,
        '--admin-email' => 'admin@example.com', '--admin-firstname' => 'Ada', '--admin-lastname' => 'Admin', '--admin-password' => 'secret-password'])
        ->assertSuccessful();
    expect(Staff::where('email', 'admin@example.com')->exists())->toBeTrue();
});

it('skips the admin without details when non-interactive', function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
    $this->artisan('storefront:install', ['--skip-search' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Skipping admin')->assertSuccessful();
    expect(Staff::count())->toBe(0);
});

it('runs the configured search command unless skipped', function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
    Artisan::registerCommand(new ClosureCommand('test:search-index', fn () => cache()->put('indexed', true)));
    config(['storefront.install.search_command' => 'test:search-index']);

    $this->artisan('storefront:install', ['--no-interaction' => true])->assertSuccessful();
    expect(cache('indexed'))->toBeTrue();
});

it('is safe to run twice', function () {
    Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR']);
    $args = ['--skip-search' => true, '--no-interaction' => true,
        '--admin-email' => 'admin@example.com', '--admin-firstname' => 'Ada', '--admin-lastname' => 'Admin', '--admin-password' => 'secret-password'];
    $this->artisan('storefront:install', $args)->assertSuccessful();
    $counts = fn () => [Currency::count(), TaxRate::count(), ShippingZone::count(), ShippingMethod::count(), ShippingRate::count(), Staff::count()];
    $before = $counts();

    $this->artisan('storefront:install', $args)->expectsOutputToContain('Admin admin@example.com already exists')->assertSuccessful();
    expect($counts())->toBe($before);
});
