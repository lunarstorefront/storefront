<?php

use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Lunar\Storefront\Actions\Account\FindCustomerOrder;
use Lunar\Storefront\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::query()->create([
        'name' => 'Sam Fitter',
        'email' => 'sam@example.com',
        'password' => 'secret',
    ]);
});

test('it finds one of the customer placed orders by its reference', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id, 'reference' => '00000042', 'placed_at' => now()]);

    $found = (new FindCustomerOrder)->get($this->user, '00000042', ['physicalLines']);

    expect($found?->is($order))->toBeTrue()
        ->and($found->relationLoaded('physicalLines'))->toBeTrue();
});

test('it finds an order placed under a customer the user belongs to', function () {
    $customer = Customer::factory()->create();
    $this->user->customers()->attach($customer);
    Order::factory()->create(['customer_id' => $customer->id, 'reference' => 'COMPANY', 'placed_at' => now()]);

    expect((new FindCustomerOrder)->get($this->user, 'COMPANY'))->not->toBeNull();
});

test('it finds nothing for an order that is not theirs or not placed', function (array $attributes) {
    $other = User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']);
    Order::factory()->create([
        'reference' => 'NOPE',
        'placed_at' => now(),
        ...array_map(fn ($value) => $value === 'other' ? $other->id : $value, $attributes),
    ]);

    expect((new FindCustomerOrder)->get($this->user, 'NOPE'))->toBeNull();
})->with([
    'another user' => [['user_id' => 'other']],
    'a guest order' => [['user_id' => null]],
    'not placed' => [['user_id' => 1, 'placed_at' => null]],
]);
