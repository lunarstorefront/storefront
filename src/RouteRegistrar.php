<?php

namespace Lunar\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lunar\Core\Exceptions\Carts\CartException;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\CartLine;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\Http\Controllers\Account\DestroyAddressController;
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
        // {id} is the line's public id, the one the CartLine DTO exposes.
        Route::put('cart/lines/{id}', function (Request $request, string $id) {
            $cart = CartSession::current();

            $request->validate([
                'quantity' => [
                    'required',
                    'integer',
                    'min:1',
                    new InStock($cart, cartLineId: $id),
                ],
            ]);

            // InStock has already rejected a line outside this cart.
            $line = $cart?->lines->firstWhere('public_id', $id);

            /**
             * Through the cart, not a direct write, so Lunar's update_cart_line
             * validators run and the cart is recalculated.
             */
            try {
                $cart?->updateLine($line->id, (int) $request->input('quantity'));
            } catch (CartException $e) {
                throw ValidationException::withMessages([
                    'quantity' => $e->errors()->first(),
                ]);
            }

            return back();
        })->name('lunar.storefront.cart.lines.quantity')->middleware(['web']);

        Route::delete('cart/lines', function (Request $request) {
            $cart = CartSession::current();

            $request->validate([
                'id' => [
                    'required',
                    'string',
                    Rule::exists(CartLine::class, 'public_id')->where('cart_id', $cart?->id),
                ],
            ]);

            $cart?->lines()->where('public_id', $request->input('id'))->delete();

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
                    'integer',
                    'min:1',
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
        })
            // Each attempt says whether a code is valid, so cap the guesses.
            ->middleware(['web', 'throttle:10,1'])
            ->name('lunar.storefront.cart.discount');

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
        // The controller serves create and update; create has no {id} to bind.
        Route::post('/account/addresses', StoreController::class)->defaults('id', '')->middleware(['auth', 'web'])->name('storefront.account.addresses');
        Route::put('/account/addresses/{id}', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.address');
        Route::delete('/account/addresses/{id}', DestroyAddressController::class)->middleware(['auth', 'web'])->name('storefront.account.address.delete');
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
        Route::get('api/query-suggestions', GetQuerySuggestionsController::class)->middleware(['web', 'throttle:120,1'])->name('storefront.query-suggestions');
    }

    protected static function authRoutes(): void
    {
        Route::get('/api/auth/codes', GetTwoFactorCodesController::class)->middleware(['web', 'auth'])->name('auth.codes');
    }
}
