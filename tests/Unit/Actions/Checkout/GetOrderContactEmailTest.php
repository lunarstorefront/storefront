<?php

use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress;
use Lunar\Storefront\Actions\Checkout\GetOrderContactEmail;
use Lunar\Storefront\Tests\Stubs\User;

function orderWithAddresses(?string $billing, ?string $shipping, array $attributes = []): Order
{
    $order = Order::factory()->create(['placed_at' => now()] + $attributes);
    OrderAddress::factory()->for($order)->create(['type' => 'billing', 'contact_email' => $billing]);
    OrderAddress::factory()->for($order)->create(['type' => 'shipping', 'contact_email' => $shipping]);

    return $order->fresh();
}

it('prefers the billing contact email', function () {
    $order = orderWithAddresses('billing@example.com', 'shipping@example.com');

    expect((new GetOrderContactEmail)->handle($order, session()->driver()))->toBe('billing@example.com');
});

it('uses the shipping contact email when billing has none', function () {
    $order = orderWithAddresses(null, 'shipping@example.com');

    expect((new GetOrderContactEmail)->handle($order, session()->driver()))->toBe('shipping@example.com');
});

it('falls back to the order user email', function () {
    $user = User::query()->create(['name' => 'Sam', 'email' => 'user@example.com', 'password' => 'secret']);
    $order = orderWithAddresses(null, null, ['user_id' => $user->id]);

    expect((new GetOrderContactEmail)->handle($order, session()->driver()))->toBe('user@example.com');
});

it('returns null when nothing carries an email', function () {
    $order = orderWithAddresses(null, null);
    session()->put('lunar.checkout.completed', ['uuid' => 'abc']);

    expect((new GetOrderContactEmail)->handle($order, session()->driver()))->toBeNull();
});
