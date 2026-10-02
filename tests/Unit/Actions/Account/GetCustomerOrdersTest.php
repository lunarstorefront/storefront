<?php

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Storefront\Actions\Account\GetCustomerOrders;
use Lunar\Storefront\Data\Order as OrderData;
use Lunar\Storefront\Tests\Stubs\User;

beforeEach(function () {
    Currency::factory()->create(['code' => 'GBP', 'default' => true, 'decimal_places' => 2]);
    $this->user = User::query()->create([
        'name' => 'Sam Fitter',
        'email' => 'sam@example.com',
        'password' => 'secret',
    ]);
});

/** A placed order for $owner, with one physical line. */
function placedOrderFor(?User $owner, array $attributes = [], array $line = []): Order
{
    $order = Order::factory()->create([
        'user_id' => $owner?->id,
        'currency_code' => 'GBP',
        'placed_at' => now(),
        ...$attributes,
    ]);

    OrderLine::factory()->for($order)->create(['type' => 'physical', ...$line]);

    return $order;
}

test('it lists only the placed orders the customer owns, newest first', function () {
    $older = placedOrderFor($this->user, ['reference' => 'OLDER', 'placed_at' => now()->subDays(3)]);
    $newer = placedOrderFor($this->user, ['reference' => 'NEWER', 'placed_at' => now()->subDay()]);
    placedOrderFor($this->user, ['reference' => 'UNPLACED', 'placed_at' => null]);
    placedOrderFor(User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']), ['reference' => 'THEIRS']);
    placedOrderFor(null, ['reference' => 'GUEST']);

    $page = (new GetCustomerOrders)->get($this->user);

    expect($page->total())->toBe(2)
        ->and(collect($page->items())->pluck('reference')->all())->toBe(['NEWER', 'OLDER'])
        ->and($page->items()[0])->toBeInstanceOf(OrderData::class);
});

test('it includes orders placed under a customer the user belongs to', function () {
    $customer = Customer::factory()->create();
    $this->user->customers()->attach($customer);
    placedOrderFor(null, ['reference' => 'COMPANY', 'customer_id' => $customer->id]);

    $page = (new GetCustomerOrders)->get($this->user);

    expect(collect($page->items())->pluck('reference')->all())->toBe(['COMPANY']);
});

test('it carries each order lines and shipping lines for the list cards', function () {
    $order = placedOrderFor($this->user, line: ['description' => 'Alpha2 pump', 'identifier' => 'KJ-100']);
    OrderLine::factory()->for($order)->create([
        'type' => 'shipping',
        'purchasable_type' => null,
        'purchasable_id' => null,
        'description' => 'Standard delivery',
    ]);

    $card = (new GetCustomerOrders)->get($this->user)->items()[0]->toArray();

    expect($card['physicalLines'][0]['description'])->toBe('Alpha2 pump')
        ->and($card['shippingLines'][0]['description'])->toBe('Standard delivery');
});

test('it pages the orders', function () {
    foreach (range(1, 3) as $day) {
        placedOrderFor($this->user, ['placed_at' => now()->subDays($day)]);
    }

    $page = (new GetCustomerOrders)->get($this->user, perPage: 2);

    expect($page->total())->toBe(3)
        ->and($page->items())->toHaveCount(2)
        ->and($page->lastPage())->toBe(2);
});

test('it searches by order number, customer reference, SKU and product name', function (string $term) {
    placedOrderFor($this->user, ['reference' => '00000042', 'customer_reference' => 'JOB-4471'], ['identifier' => 'KJ-100', 'description' => 'Alpha2 25-60 Circulation Pump']);
    placedOrderFor($this->user, ['reference' => '00000043', 'customer_reference' => 'OTHER'], ['identifier' => 'ZZ-999', 'description' => 'Zone valve']);

    $page = (new GetCustomerOrders)->get($this->user, ['search' => $term]);

    expect(collect($page->items())->pluck('reference')->all())->toBe(['00000042']);
})->with(['order number' => '0042', 'customer reference' => 'job-4471', 'sku' => 'KJ-100', 'product name' => 'circulation']);

test('it filters by placed date', function () {
    placedOrderFor($this->user, ['reference' => 'JAN', 'placed_at' => '2026-01-15 10:00:00']);
    placedOrderFor($this->user, ['reference' => 'MAR', 'placed_at' => '2026-03-15 10:00:00']);

    $page = (new GetCustomerOrders)->get($this->user, ['from' => '2026-01-01', 'to' => '2026-01-31']);

    expect(collect($page->items())->pluck('reference')->all())->toBe(['JAN']);
});

test('it sorts oldest first or by highest total', function (string $sort, array $expected) {
    placedOrderFor($this->user, ['reference' => 'A', 'total' => 500, 'placed_at' => now()->subDays(3)]);
    placedOrderFor($this->user, ['reference' => 'B', 'total' => 9000, 'placed_at' => now()->subDays(2)]);
    placedOrderFor($this->user, ['reference' => 'C', 'total' => 100, 'placed_at' => now()->subDay()]);

    $page = (new GetCustomerOrders)->get($this->user, ['sort' => $sort]);

    expect(collect($page->items())->pluck('reference')->all())->toBe($expected);
})->with([
    'oldest' => ['oldest', ['A', 'B', 'C']],
    'highest total' => ['total', ['B', 'A', 'C']],
]);

test('it narrows the list with a caller scope, such as a status tab', function () {
    placedOrderFor($this->user, ['reference' => 'OPEN', 'fulfilment_status' => 'unfulfilled']);
    placedOrderFor($this->user, ['reference' => 'SENT', 'fulfilment_status' => 'fulfilled']);

    $page = (new GetCustomerOrders)->get(
        $this->user,
        scope: fn (Builder $query) => $query->where('fulfilment_status', 'fulfilled'),
    );

    expect(collect($page->items())->pluck('reference')->all())->toBe(['SENT']);
});

test('it exposes the owned placed orders as a query for counts', function () {
    placedOrderFor($this->user);
    placedOrderFor($this->user, ['placed_at' => null]);
    placedOrderFor(null);

    expect((new GetCustomerOrders)->query($this->user)->count())->toBe(1);
});
