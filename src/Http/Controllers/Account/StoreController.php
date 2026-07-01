<?php

namespace Lunar\Storefront\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreController extends Controller
{
    public function __invoke(?string $id, Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => ['required', 'string',  'max:255'],
            'last_name' => ['nullable', 'string',  'max:255'],
            'company_name' => ['nullable', 'string',  'max:255'],
            'line_one' => ['nullable', 'string',  'max:64'],
            'line_two' => ['nullable', 'string',  'max:64'],
            'city' => ['required', 'string',  'max:64'],
            'state' => ['required', 'string',  'max:64'],
            'postcode' => ['required', 'string',  'max:10'],
            'country_id' => ['required'],
        ]);

        $addressData = $request->only([
            'first_name',
            'last_name',
            'company_name',
            'line_one',
            'line_two',
            'city',
            'state',
            'postcode',
            'contact_phone',
            'contact_email',
            'country_id',
            'delivery_instructions',
        ]);

        $addressData['billing_default'] = $request->input('type') == 'billing';
        $addressData['shipping_default'] = $request->input('type') != 'billing';

        if ($id) {
            $address = $request->user()->latestCustomer()->addresses()->findOrFail($id);
            $address->fill($addressData)->save();

            return back();
        }

        $request->user()->latestCustomer()->addresses()->create($addressData);

        return back();
    }
}
