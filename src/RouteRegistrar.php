<?php

namespace Lunar\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\CartLine;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\Http\Controllers\Account\StoreController;
use Lunar\Storefront\Http\Controllers\Auth\GetTwoFactorCodesController;
use Lunar\Storefront\Http\Controllers\Checkout\OrderIssueController;
use Lunar\Storefront\Http\Controllers\Checkout\SuccessController;
use Lunar\Storefront\Http\Controllers\GetQuerySuggestionsController;
use Lunar\Storefront\Http\Controllers\SetCurrencyController;
use Lunar\Storefront\Rules\InStock;

class RouteRegistrar
{
    /**
     * Register the storefront routes.
     *
     * Pass an array of group names to register only those groups; an empty array
     * (the default) registers every group, so existing `register()` calls keep
     * registering everything. This lets a host application opt in to just the
     * routes it needs and avoid route-name clashes with its own (e.g. its own
     * account routes).
     *
     * @param  string[]  $only  Groups to register. Valid: cart, account, checkout,
     *                          currency, suggestions, auth. Empty = all.
     */
    public static function register(array $only = []): void
    {
        $groups = [
            'cart' => static fn () => static::cartRoutes(),
            'account' => static fn () => static::accountRoutes(),
            'checkout' => static fn () => static::checkoutRoutes(),
            'currency' => static fn () => static::currencyRoutes(),
            'suggestions' => static fn () => static::suggestionRoutes(),
            'auth' => static fn () => static::authRoutes(),
        ];

        foreach ($only === [] ? array_keys($groups) : $only as $group) {
            if (isset($groups[$group])) {
                $groups[$group]();
            }
        }
    }

    protected static function cartRoutes(): void
    {
        Route::put('cart/lines/{id}', function (Request $request, int $id) {
            $cart = CartSession::current();

            $request->validate([
                'quantity' => [
                    'required',
                    'numeric',
                    new InStock($cart, cartLineId: $id),
                ],
            ]);

            $cart?->lines()->where('id', $id)->update(['quantity' => $request->input('quantity')]);

            return back();
        })->name('lunar.storefront.cart.lines.quantity')->middleware(['web']);

        Route::delete('cart/lines', function (Request $request) {
            $cart = CartSession::current();

            $request->validate([
                'id' => 'required|exists:'.CartLine::class.',id',
            ]);

            $cart?->lines()->where('id', $request->input('id'))->delete();

            return back();
        })->name('lunar.storefront.cart.lines.delete')->middleware(['web']);

        Route::post('cart/lines', function (Request $request) {
            $request->validate([
                'sku' => 'required|exists:'.ProductVariant::class.',sku',
            ]);

            $cart = CartSession::current();

            $request->validate([
                'quantity' => [
                    'required',
                    'numeric',
                    new InStock($cart),
                ],
            ]);

            $purchasable = Storefront::variants()->getBySku($request->input('sku'));

            CartSession::add($purchasable, $request->input('quantity'));

            return back();
        })->middleware(['web'])->name('lunar.storefront.cart.lines');

        Route::post('carts/discount', function (Request $request) {
            $request->validate([
                'coupon' => [
                    'required',
                    new Rules\ValidCoupon,
                ],
            ]);

            $cart = CartSession::current();

            $cart?->update([
                'coupon_code' => $request->post('coupon'),
            ]);

            return back();
        })->middleware(['web'])->name('lunar.storefront.cart.discount');

        Route::delete('carts/discount', function (Request $request) {
            $cart = CartSession::current();

            $cart?->update([
                'coupon_code' => null,
            ]);

            return back();
        })->middleware(['web'])->name('lunar.storefront.cart.discount.delete');
    }

    protected static function accountRoutes(): void
    {
        Route::post('/account/addresses', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.addresses');
        Route::put('/account/addresses/{id}', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.address');
    }

    protected static function checkoutRoutes(): void
    {
        Route::get('checkout/success', SuccessController::class)
            ->middleware(['web'])->name('checkout.success');

        Route::get('checkout/order-issue', OrderIssueController::class)
            ->middleware(['web'])->name('checkout.order-issue');
    }

    protected static function currencyRoutes(): void
    {
        Route::post('/api/currency', SetCurrencyController::class)->middleware(['web'])->name('storefront.currency');
    }

    protected static function suggestionRoutes(): void
    {
        Route::get('api/query-suggestions', GetQuerySuggestionsController::class)->name('storefront.query-suggestions');
    }

    protected static function authRoutes(): void
    {
        Route::get('/api/auth/codes', GetTwoFactorCodesController::class)->middleware(['web', 'auth'])->name('auth.codes');
    }
}
