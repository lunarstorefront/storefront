<?php

use Illuminate\Foundation\Auth\User as PlainUser;
use Illuminate\Support\Facades\Route;
use Lunar\Core\Models\Address;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Storefront\RouteRegistrar;
use Lunar\Storefront\Tests\Stubs\User;

beforeEach(function () {
    CustomerGroup::factory()->create(['default' => true]);
    $this->country = Country::factory()->create(['iso2' => 'GB']);

    $this->user = User::query()->create([
        'name' => 'Sam Fitter',
        'email' => 'sam@example.com',
        'password' => 'secret',
    ]);
    $this->customer = Customer::factory()->create();
    $this->user->customers()->attach($this->customer);

    RouteRegistrar::register(['account']);
    Route::getRoutes()->refreshNameLookups();
});

/** @return array<string, mixed> */
function addressPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Sam',
        'last_name' => 'Fitter',
        'line_one' => '1 Site Road',
        'city' => 'Bury',
        'state' => 'Lancashire',
        'postcode' => 'BL9 0SA',
        'country_id' => test()->country->id,
        ...$overrides,
    ];
}

it('saves an address with valid contact details', function () {
    $this->actingAs($this->user)
        ->post(route('storefront.account.addresses'), addressPayload([
            'contact_email' => 'site@example.com',
            'contact_phone' => '01234 567890',
            'delivery_instructions' => 'Gate code 1234',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $address = $this->customer->addresses()->sole();

    expect($address->contact_email)->toBe('site@example.com')
        ->and($address->contact_phone)->toBe('01234 567890')
        ->and($address->delivery_instructions)->toBe('Gate code 1234');
});

it('rejects invalid address fields', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->post(route('storefront.account.addresses'), addressPayload($overrides))
        ->assertSessionHasErrors($field);

    expect($this->customer->addresses()->exists())->toBeFalse();
})->with([
    'contact email that is not an email' => [['contact_email' => 'not-an-email'], 'contact_email'],
    'contact email too long' => [['contact_email' => str_repeat('a', 250).'@example.com'], 'contact_email'],
    'contact phone too long' => [['contact_phone' => str_repeat('1', 33)], 'contact_phone'],
    'contact phone not a string' => [['contact_phone' => ['01234']], 'contact_phone'],
    'delivery instructions too long' => [['delivery_instructions' => str_repeat('x', 1001)], 'delivery_instructions'],
    'country that does not exist' => [['country_id' => 999999], 'country_id'],
    'country that is not an id' => [['country_id' => 'GB'], 'country_id'],
    'no first address line' => [['line_one' => ''], 'line_one'],
]);

it('saves an address with no last name', function () {
    $this->actingAs($this->user)
        ->post(route('storefront.account.addresses'), addressPayload(['last_name' => '']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->customer->addresses()->sole()->last_name)->toBe('');
});

it('gives an account with no customer one instead of failing', function () {
    $user = User::query()->create([
        'name' => 'Imported Customer',
        'email' => 'imported@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user)
        ->post(route('storefront.account.addresses'), addressPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->refresh()->latestCustomer()?->addresses()->count())->toBe(1);
});

it('only updates an address that belongs to the account', function () {
    $other = Customer::factory()->create();
    $theirs = Address::factory()->for($other)->create(['country_id' => $this->country->id, 'first_name' => 'Theirs']);

    $this->actingAs($this->user)
        ->put(route('storefront.account.address', $theirs->id), addressPayload(['first_name' => 'Mine']))
        ->assertNotFound();

    expect($theirs->refresh()->first_name)->toBe('Theirs');
});

it('updates one of the account\'s own addresses', function () {
    $address = Address::factory()->for($this->customer)->create(['country_id' => $this->country->id, 'first_name' => 'Old']);

    $this->actingAs($this->user)
        ->put(route('storefront.account.address', $address->id), addressPayload(['first_name' => 'New']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($address->refresh()->first_name)->toBe('New');
});

it('keeps the stored last name when an update leaves the field out', function () {
    $address = Address::factory()->for($this->customer)->create(['country_id' => $this->country->id, 'last_name' => 'Fitter']);

    $payload = addressPayload(['first_name' => 'Sam']);
    unset($payload['last_name']);

    $this->actingAs($this->user)
        ->put(route('storefront.account.address', $address->id), $payload)
        ->assertSessionHasNoErrors();

    expect($address->refresh()->last_name)->toBe('Fitter');
});

it('refuses a signed-in user model that is not a Lunar user', function () {
    $plain = new class extends PlainUser
    {
        protected $table = 'users';

        protected $guarded = [];
    };
    $user = $plain->newQuery()->create(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        // Invalid on purpose: the 403 comes before validation, not a 302 with errors.
        ->post(route('storefront.account.addresses'), addressPayload(['line_one' => '']))
        ->assertForbidden();
});

it('saves a new address sent without a last name at all', function () {
    $payload = addressPayload();
    unset($payload['last_name']);

    $this->actingAs($this->user)
        ->post(route('storefront.account.addresses'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->customer->addresses()->sole()->last_name)->toBe('');
});
