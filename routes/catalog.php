<?php

\Illuminate\Support\Facades\Route::post('products/variants/hash', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'options' => 'required|array',
    ]);

    return response()->json([
        'hash' => (new \Lunar\Storefront\Managers\VariantManager)->encryptOptions(
            $request->input('options', [])
        )
    ]);
})->name('storefront.api.products-hash');