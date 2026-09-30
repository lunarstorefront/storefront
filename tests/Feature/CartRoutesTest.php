<?php

use Illuminate\Support\Facades\Route;
use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\CartLine;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\RouteRegistrar;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    $this->channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();
    TaxZone::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $this->channel->id,
        'currency_id' => $this->currency->id,
        'language_id' => $language->id,
    ]);

    RouteRegistrar::register(['cart']);
    Route::getRoutes()->refreshNameLookups();
});

/**
 * The shopper's cart, in session, holding one line of a variant with the
 * given stock.
 */
function cartWithLine(int $quantity, int $stock): CartLine
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(test()->productType))
        ->for(test()->taxClass)
        ->inStock($stock)
        ->create([
            'sku' => 'LINE-'.uniqid(),
            'selling_policy' => SellingPolicy::InStock,
        ]);

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => test()->currency->id,
        'price' => 1000,
    ]);

    $cart = Cart::factory()->create([
        'channel_id' => test()->channel->id,
        'currency_id' => test()->currency->id,
    ]);

    CartSession::use($cart);

    return CartLine::factory()->create([
        'cart_id' => $cart->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => $quantity,
    ]);
}

/**
 * Another shopper's cart line: a valid id the current shopper does not own.
 */
function foreignLine(): CartLine
{
    $line = cartWithLine(quantity: 1, stock: 10);

    CartSession::forget(delete: false);

    return $line;
}

test('it updates a line to the new quantity', function () {
    $line = cartWithLine(quantity: 2, stock: 10);

    $this->put(route('lunar.storefront.cart.lines.quantity', $line->id), ['quantity' => 4])
        ->assertSessionHasNoErrors();

    expect($line->fresh()->quantity)->toBe(4);
});

test('it checks stock against the new quantity, not the old one added to it', function () {
    $line = cartWithLine(quantity: 5, stock: 8);

    $this->put(route('lunar.storefront.cart.lines.quantity', $line->id), ['quantity' => 6])
        ->assertSessionHasNoErrors();

    expect($line->fresh()->quantity)->toBe(6);
});

test('it still refuses an update beyond stock', function () {
    $line = cartWithLine(quantity: 5, stock: 8);

    $this->put(route('lunar.storefront.cart.lines.quantity', $line->id), ['quantity' => 9])
        ->assertSessionHasErrors('quantity');

    expect($line->fresh()->quantity)->toBe(5);
});

test('it counts what the cart already holds when adding more', function () {
    $line = cartWithLine(quantity: 5, stock: 8);

    $this->post(route('lunar.storefront.cart.lines'), [
        'sku' => $line->purchasable->sku,
        'quantity' => 4,
    ])->assertSessionHasErrors(['quantity' => 'Insufficient stock for total quantity 9']);

    expect($line->fresh()->quantity)->toBe(5);
});

test('it refuses a quantity that is not a whole number of at least one', function (mixed $quantity) {
    $line = cartWithLine(quantity: 2, stock: 10);

    $this->put(route('lunar.storefront.cart.lines.quantity', $line->id), ['quantity' => $quantity])
        ->assertSessionHasErrors('quantity');

    expect($line->fresh()->quantity)->toBe(2);
})->with([
    'zero' => 0,
    'negative' => -3,
    'decimal' => 1.5,
]);

test('it refuses to add a quantity that is not a whole number of at least one', function (mixed $quantity) {
    $line = cartWithLine(quantity: 2, stock: 10);

    $this->post(route('lunar.storefront.cart.lines'), [
        'sku' => $line->purchasable->sku,
        'quantity' => $quantity,
    ])->assertSessionHasErrors('quantity');

    expect($line->fresh()->quantity)->toBe(2);
})->with([
    'zero' => 0,
    'negative' => -3,
    'decimal' => 1.5,
]);

test('it treats a line in another cart as not found when updating', function () {
    $foreign = foreignLine();
    cartWithLine(quantity: 1, stock: 10);

    $this->put(route('lunar.storefront.cart.lines.quantity', $foreign->id), ['quantity' => 2])
        ->assertSessionHasErrors(['quantity' => 'Cart line not found.']);

    expect($foreign->fresh()->quantity)->toBe(1);
});

test('it validates a delete against the current cart only', function () {
    $foreign = foreignLine();
    cartWithLine(quantity: 1, stock: 10);

    $this->delete(route('lunar.storefront.cart.lines.delete'), ['id' => $foreign->id])
        ->assertSessionHasErrors('id');

    $this->delete(route('lunar.storefront.cart.lines.delete'), ['id' => 999999])
        ->assertSessionHasErrors('id');

    expect($foreign->fresh())->not->toBeNull();
});

test('it deletes a line from the current cart', function () {
    $line = cartWithLine(quantity: 1, stock: 10);

    $this->delete(route('lunar.storefront.cart.lines.delete'), ['id' => $line->id])
        ->assertSessionHasNoErrors();

    expect($line->fresh())->toBeNull();
});
