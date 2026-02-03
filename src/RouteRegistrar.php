<?php

namespace Lunar\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Scout;
use Lunar\Facades\CartSession;
use Lunar\Models\CartLine;
use Lunar\Models\ProductVariant;
use Lunar\Rules\ValidCoupon;
use Lunar\Storefront\Data\SearchQueryHit;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\Http\Controllers\Account\StoreController;
use Lunar\Storefront\Http\Controllers\Auth\GetTwoFactorCodesController;
use Lunar\Storefront\Http\Controllers\GetQuerySuggestionsController;
use Lunar\Storefront\Http\Controllers\SetCurrencyController;
use Lunar\Storefront\Rules\InStock;
use Meilisearch\Contracts\SearchQuery;

class RouteRegistrar
{
    public static function register()
    {
        Route::put('cart/lines/{id}', function (Request $request, int $id) {
            $cart = CartSession::current();

            $request->validate([
                'quantity' => [
                    'required',
                    'numeric',
                    new InStock($cart, cartLineId: $id)
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
                    new InStock($cart)
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
                    new ValidCoupon,
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

        Route::post('/account/addresses', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.addresses');
        Route::put('/account/addresses/{id}', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.address');

        Route::get('api/query-suggestions', GetQuerySuggestionsController::class)->name('storefront.query-suggestions');

        Route::post('/api/currency', SetCurrencyController::class)->middleware(['web'])->name('storefront.currency');

        Route::get('/api/auth/codes', GetTwoFactorCodesController::class)->middleware(['web', 'auth'])->name('auth.codes');
    }
}
