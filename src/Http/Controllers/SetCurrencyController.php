<?php

namespace Lunar\Storefront\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Core\Models\Currency;
use Lunar\Storefront\Facades\Storefront;

class SetCurrencyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'currency_code' => ['required', 'exists:'.Currency::class.',code'],
        ]);

        Storefront::setCurrency($request->input('currency_code'));

        return back();
    }
}