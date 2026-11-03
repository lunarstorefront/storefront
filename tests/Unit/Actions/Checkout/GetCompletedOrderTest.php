<?php

use Lunar\Core\Models\Order;
use Lunar\Storefront\Actions\Checkout\GetCompletedOrder;

it('returns the placed order from the session', function () {
    $order = Order::factory()->create(['placed_at' => now()]);
    session()->put('lunar.checkout.completed', ['uuid' => 'abc', 'order_reference' => (string) $order->id]);

    expect((new GetCompletedOrder)->handle(session()->driver())?->id)->toBe($order->id);
});

it('ignores missing, tampered or unplaced handovers', function (mixed $value) {
    $unplaced = Order::factory()->create(['placed_at' => null]);
    session()->put('lunar.checkout.completed', $value === 'unplaced' ? ['order_reference' => $unplaced->id] : $value);

    expect((new GetCompletedOrder)->handle(session()->driver()))->toBeNull();
})->with([
    'nothing' => [null],
    'string' => ['123'],
    'non numeric' => [['order_reference' => '1 OR 1=1']],
    'unknown id' => [['order_reference' => 999999]],
    'unplaced' => ['unplaced'],
]);

it('loads the requested relations', function () {
    $order = Order::factory()->create(['placed_at' => now()]);
    session()->put('lunar.checkout.completed', ['order_reference' => $order->id]);

    $found = (new GetCompletedOrder)->handle(session()->driver(), ['billingAddress']);

    expect($found?->relationLoaded('billingAddress'))->toBeTrue();
});
