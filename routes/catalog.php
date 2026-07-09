<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lunar\Storefront\Managers\VariantManager;

Route::post('products/variants/hash', function (Request $request) {
    $request->validate([
        'options' => 'required|array',
    ]);

    return response()->json([
        'hash' => (new VariantManager)->encryptOptions(
            $request->input('options', [])
        ),
    ]);
})->name('storefront.api.products-hash');
