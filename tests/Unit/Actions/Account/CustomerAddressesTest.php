<?php

use Lunar\Core\Models\Address;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress;
use Lunar\Storefront\Actions\Account\DeleteCustomerAddress;
use Lunar\Storefront\Actions\Account\FindCustomerAddress;
use Lunar\Storefront\Actions\Account\GetCustomerAddresses;
use Lunar\Storefront\Actions\Account\GetCustomerForUser;
use Lunar\Storefront\Actions\Account\SaveCustomerAddress;
use Lunar\Storefront\Actions\Account\SetDefaultCustomerAddress;
use Lunar\Storefront\Tests\Stubs\User;

beforeEach(function () {
    $this->group = CustomerGroup::factory()->create(['default' => true]);
    $this->country = Country::factory()->create(['iso2' => 'GB']);
    $this->user = User::query()->create([
        'name' => 'Sam Fitter',
        'email' => 'sam@example.com',
        'password' => 'secret',
    ]);
});

/** @return array<string, mixed> */
function addressAttributes(array $overrides = []): array
{
    return [
        'title' => 'Yard',
        'first_name' => 'Sam',
        'last_name' => 'Fitter',
        'line_one' => '1 Site Road',
        'city' => 'Bury',
        'postcode' => 'BL9 0SA',
        'country_id' => test()->country->id,
        ...$overrides,
    ];
}

function saveAddress(array $overrides = [], ?Address $address = null, array $defaults = []): Address
{
    return (new SaveCustomerAddress)->save(test()->user, addressAttributes($overrides), $address, $defaults);
}

test('it creates a customer for a user that has none, in the default group', function () {
    $customer = (new GetCustomerForUser)->get($this->user);

    expect($customer->first_name)->toBe('Sam')
        ->and($customer->last_name)->toBe('Fitter')
        ->and($this->user->customers()->whereKey($customer->id)->exists())->toBeTrue()
        ->and($customer->customerGroups->pluck('id')->all())->toBe([$this->group->id]);
});

test('it reuses the user\'s latest customer', function () {
    $customer = Customer::factory()->create();
    $this->user->customers()->attach($customer);

    expect((new GetCustomerForUser)->get($this->user)->is($customer))->toBeTrue()
        ->and(Customer::query()->count())->toBe(1);
});

test('the first address becomes the default for delivery and billing', function () {
    $address = saveAddress();

    expect($address->shipping_default)->toBeTrue()
        ->and($address->billing_default)->toBeTrue()
        ->and($address->customer_id)->toBe($this->user->latestCustomer()->id);
});

test('a later address is not a default unless asked', function () {
    saveAddress();
    $second = saveAddress(['title' => 'Office']);

    expect($second->shipping_default)->toBeFalse()
        ->and($second->billing_default)->toBeFalse();
});

test('making an address the default delivery address leaves exactly one', function () {
    $first = saveAddress();
    $second = saveAddress(['title' => 'Office'], defaults: ['shipping']);

    expect($second->shipping_default)->toBeTrue()
        ->and($first->fresh()->shipping_default)->toBeFalse()
        ->and($first->fresh()->billing_default)->toBeTrue()
        ->and(Address::query()->where('shipping_default', true)->count())->toBe(1);
});

test('setting the default billing address leaves exactly one', function () {
    $first = saveAddress();
    $second = saveAddress(['title' => 'Office']);

    (new SetDefaultCustomerAddress)->set($this->user, $second, 'billing');

    expect($second->fresh()->billing_default)->toBeTrue()
        ->and($first->fresh()->billing_default)->toBeFalse()
        ->and($first->fresh()->shipping_default)->toBeTrue();
});

test('it rejects an unknown default type', function () {
    (new SetDefaultCustomerAddress)->set($this->user, saveAddress(), 'pickup');
})->throws(InvalidArgumentException::class);

test('editing an address updates it without touching the defaults', function () {
    $address = saveAddress();

    $updated = saveAddress(['line_one' => '2 Yard Lane', 'shipping_default' => false], $address);

    expect($updated->line_one)->toBe('2 Yard Lane')
        ->and($updated->shipping_default)->toBeTrue();
});

test('it lists the user\'s addresses with the defaults first', function () {
    saveAddress(['title' => 'Default']);
    saveAddress(['title' => 'Other']);
    $other = User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']);
    (new SaveCustomerAddress)->save($other, addressAttributes(['title' => 'Not mine']));

    $titles = (new GetCustomerAddresses)->get($this->user)->pluck('title')->all();

    expect($titles)->toBe(['Default', 'Other']);
});

test('it finds only the user\'s own address', function () {
    $mine = saveAddress();
    $other = User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']);
    $theirs = (new SaveCustomerAddress)->save($other, addressAttributes());

    expect((new FindCustomerAddress)->get($this->user, $mine->public_id)?->is($mine))->toBeTrue()
        ->and((new FindCustomerAddress)->get($this->user, $theirs->public_id))->toBeNull();
});

test('a user with no customer has no addresses and creates no customer by looking', function () {
    expect((new GetCustomerAddresses)->get($this->user))->toBeEmpty()
        ->and((new FindCustomerAddress)->get($this->user, 'anything'))->toBeNull()
        ->and(Customer::query()->count())->toBe(0);
});

test('deleting a default promotes the newest remaining address', function () {
    $first = saveAddress(['title' => 'First']);
    $second = saveAddress(['title' => 'Second']);
    $third = saveAddress(['title' => 'Third']);

    (new DeleteCustomerAddress)->delete($this->user, $first);

    expect(Address::query()->find($first->id))->toBeNull()
        ->and($third->fresh()->shipping_default)->toBeTrue()
        ->and($third->fresh()->billing_default)->toBeTrue()
        ->and($second->fresh()->shipping_default)->toBeFalse();
});

test('deleting the last address leaves none', function () {
    (new DeleteCustomerAddress)->delete($this->user, saveAddress());

    expect((new GetCustomerAddresses)->get($this->user))->toBeEmpty();
});

test('deleting an address leaves past orders\' addresses alone', function () {
    $address = saveAddress();
    $orderAddress = OrderAddress::factory()->for(Order::factory())->create([
        'type' => 'shipping',
        'line_one' => '1 Site Road',
        'postcode' => 'BL9 0SA',
        'country_id' => $this->country->id,
    ]);

    (new DeleteCustomerAddress)->delete($this->user, $address);

    expect($orderAddress->fresh()->line_one)->toBe('1 Site Road');
});
