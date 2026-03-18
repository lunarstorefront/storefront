<?php

namespace Lunar\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lunar\Catalog\Models\ProductVariant;
use Lunar\Sales\Facades\CartSession;
use Lunar\Sales\Models\CartLine;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\Http\Controllers\Account\StoreController;
use Lunar\Storefront\Http\Controllers\Auth\GetTwoFactorCodesController;
use Lunar\Storefront\Http\Controllers\Checkout\CreateDraftOrderController;
use Lunar\Storefront\Http\Controllers\Checkout\OrderIssueController;
use Lunar\Storefront\Http\Controllers\Checkout\ProcessingController;
use Lunar\Storefront\Http\Controllers\Checkout\SuccessController;
use Lunar\Storefront\Http\Controllers\GetQuerySuggestionsController;
use Lunar\Storefront\Http\Controllers\SetCurrencyController;
use Lunar\Storefront\Rules\InStock;

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

        Route::post('/account/addresses', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.addresses');
        Route::put('/account/addresses/{id}', StoreController::class)->middleware(['auth', 'web'])->name('storefront.account.address');
        Route::get('api/query-suggestions', GetQuerySuggestionsController::class)->name('storefront.query-suggestions');
        Route::post('/api/currency', SetCurrencyController::class)->middleware(['web'])->name('storefront.currency');
        Route::get('/api/auth/codes', GetTwoFactorCodesController::class)->middleware(['web', 'auth'])->name('auth.codes');

        Route::post('checkout/draft-order', CreateDraftOrderController::class)
            ->middleware(['web'])->name('checkout.draft-order');

        Route::get('checkout/processing', ProcessingController::class)
            ->middleware(['web'])->name('checkout.processing');

        Route::get('checkout/success', SuccessController::class)
            ->middleware(['web'])->name('checkout.success');

        Route::get('checkout/order-issue', OrderIssueController::class)
            ->middleware(['web'])->name('checkout.order-issue');
    }
}
