<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxZone;
use Lunar\Storefront\Actions\Cart\BuyNow;
use Lunar\Storefront\Http\Middleware\RestoreBasketAfterBuyNow;
use Lunar\Storefront\Tests\Stubs\User;

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

    config(['auth.providers.users.model' => User::class]);
    Auth::forgetGuards();

    Route::middleware(['web', RestoreBasketAfterBuyNow::class])->group(function () {
        Route::get('/products/pump', fn () => 'product')->name('product');
        Route::get('/checkout/session', fn () => 'checkout')->name('lunar.checkout.show');
        Route::post('/login', fn () => 'login')->name('login');
    });
    Route::getRoutes()->refreshNameLookups();
});

function buyNowVariant(): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(test()->productType))
        ->for(test()->taxClass)
        ->inStock(10)
        ->create(['sku' => 'BN-'.uniqid(), 'selling_policy' => SellingPolicy::InStock]);

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => test()->currency->id,
        'price' => 1000,
    ]);

    return $variant;
}

/** The shopper's basket, in session, holding two lines. */
function basket(array $attributes = []): Cart
{
    $cart = Cart::factory()->create([
        'channel_id' => test()->channel->id,
        'currency_id' => test()->currency->id,
        ...$attributes,
    ]);

    $cart->add(buyNowVariant(), 1);
    $cart->add(buyNowVariant(), 3);

    CartSession::use($cart);

    return $cart;
}

function buyNow(): BuyNow
{
    return app(BuyNow::class);
}

function sessionCartId(): ?int
{
    return session(CartSession::getSessionKey());
}

/** A user with a customer, as signing up in the storefront makes them. */
function shopper(): User
{
    $user = User::query()->create(['name' => 'Terry', 'email' => 'terry@example.com', 'password' => 'x']);
    $user->customers()->attach(Customer::factory()->create());

    return $user;
}

test('it checks out only the buy now item and leaves the basket untouched', function () {
    $basket = basket();
    $variant = buyNowVariant();

    $cart = buyNow()->start($variant, 2);

    expect($cart->id)->not->toBe($basket->id)
        ->and(sessionCartId())->toBe($cart->id)
        ->and(BuyNow::isBuyNowCart($cart))->toBeTrue()
        ->and($cart->lines)->toHaveCount(1)
        ->and($cart->lines->first()->purchasable_id)->toBe($variant->id)
        ->and($cart->lines->first()->quantity)->toBe(2)
        ->and($cart->currency_id)->toBe($basket->currency_id)
        ->and($cart->channel_id)->toBe($basket->channel_id)
        ->and($basket->fresh()->lines)->toHaveCount(2)
        ->and(buyNow()->active())->toBeTrue();
});

test('it brings the basket back and throws the buy now cart away', function () {
    $basket = basket();
    $cart = buyNow()->start(buyNowVariant(), 1);

    buyNow()->restore();

    expect(sessionCartId())->toBe($basket->id)
        ->and(Cart::query()->find($cart->id))->toBeNull()
        ->and($basket->fresh()->lines)->toHaveCount(2)
        ->and(buyNow()->active())->toBeFalse();
});

test('it keeps a buy now cart an order was placed from', function () {
    $basket = basket();
    $cart = buyNow()->start(buyNowVariant(), 1);
    Order::factory()->create(['cart_id' => $cart->id, 'placed_at' => now()]);

    buyNow()->restore();

    expect(Cart::query()->find($cart->id))->not->toBeNull()
        ->and(sessionCartId())->toBe($basket->id);
});

test('it leaves no cart in the session when there was no basket to park', function () {
    $cart = buyNow()->start(buyNowVariant(), 1);

    expect(BuyNow::isBuyNowCart($cart))->toBeTrue()
        ->and(sessionCartId())->toBe($cart->id);

    buyNow()->restore();

    expect(sessionCartId())->toBeNull()
        ->and(Cart::query()->find($cart->id))->toBeNull();
});

test('a second buy now replaces the first and still returns to the basket', function () {
    $basket = basket();
    $first = buyNow()->start(buyNowVariant(), 1);
    $second = buyNow()->start(buyNowVariant(), 1);

    expect(Cart::query()->find($first->id))->toBeNull()
        ->and(sessionCartId())->toBe($second->id);

    buyNow()->restore();

    expect(sessionCartId())->toBe($basket->id);
});

test('a signed-in shopper’s buy now cart is theirs, and their basket comes back', function () {
    $user = shopper();
    Auth::login($user);
    $basket = basket(['user_id' => $user->id, 'customer_id' => $user->latestCustomer()->id]);

    $cart = buyNow()->start(buyNowVariant(), 1);

    expect($cart->user_id)->toBe($user->id)
        ->and($cart->customer_id)->toBe($user->latestCustomer()->id);

    buyNow()->restore();

    expect(sessionCartId())->toBe($basket->id);
});

test('signing in during a buy now keeps the saved basket out of the buy now cart', function () {
    $user = shopper();
    $saved = basket(['user_id' => $user->id, 'customer_id' => $user->latestCustomer()->id]);
    CartSession::forget(delete: false);

    $guestBasket = basket();
    $cart = buyNow()->start(buyNowVariant(), 1);

    Auth::login($user);

    expect($cart->fresh()->lines)->toHaveCount(1)
        ->and($cart->fresh()->user_id)->toBe($user->id)
        ->and($cart->fresh()->customer_id)->toBe($user->latestCustomer()->id)
        ->and($saved->fresh()->lines)->toHaveCount(2)
        ->and($saved->fresh()->merged_id)->toBeNull()
        ->and(sessionCartId())->toBe($cart->id);

    // The guest basket comes back signed in, as it would have on sign-in.
    buyNow()->restore();

    expect(sessionCartId())->toBe($guestBasket->id)
        ->and($guestBasket->fresh()->user_id)->toBe($user->id);
});

test('leaving the checkout brings the basket back', function () {
    $basket = basket();
    buyNow()->start(buyNowVariant(), 1);

    $this->get('/products/pump')->assertOk();

    expect(sessionCartId())->toBe($basket->id);
});

test('pages of the checkout, and posts like its sign-in, keep the buy now cart', function (string $method, string $uri) {
    basket();
    $cart = buyNow()->start(buyNowVariant(), 1);

    $this->call($method, $uri)->assertOk();

    expect(sessionCartId())->toBe($cart->id)
        ->and(buyNow()->active())->toBeTrue();
})->with([
    'checkout page' => ['GET', '/checkout/session'],
    'sign-in' => ['POST', '/login'],
]);
