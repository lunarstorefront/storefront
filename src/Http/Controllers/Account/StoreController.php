<?php

namespace Lunar\Storefront\Http\Controllers\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Country;
use Lunar\Storefront\Actions\Account\FindCustomerAddress;
use Lunar\Storefront\Actions\Account\SaveCustomerAddress;

class StoreController extends Controller
{
    public function __construct(
        protected FindCustomerAddress $find,
        protected SaveCustomerAddress $addresses,
    ) {}

    public function __invoke(?string $id, Request $request): RedirectResponse
    {
        $user = $request->user();

        // The route is behind `auth`; a user model that is not a Lunar user
        // has no address book.
        abort_unless($user instanceof Model && $user instanceof LunarUser, 403);

        $request->validate([
            'first_name' => ['required', 'string',  'max:255'],
            'last_name' => ['nullable', 'string',  'max:255'],
            'company_name' => ['nullable', 'string',  'max:255'],
            // Lunar's addresses table requires a first line; nullable here let
            // a blank one through to a database error.
            'line_one' => ['required', 'string',  'max:64'],
            'line_two' => ['nullable', 'string',  'max:64'],
            'city' => ['required', 'string',  'max:64'],
            'state' => ['required', 'string',  'max:64'],
            'postcode' => ['required', 'string',  'max:10'],
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            // The checkout's limits for these two, so a saved address fits it.
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
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

        // last_name is optional on the form (a trade contact may give one name)
        // but NOT NULL in Lunar's addresses table, so a new address without one
        // stores ''. An update that leaves the field out keeps the stored one.
        if (! $id || array_key_exists('last_name', $addressData)) {
            $addressData['last_name'] ??= '';
        }

        $defaults = [$request->input('type') == 'billing' ? 'billing' : 'shipping'];

        // The id is the address's public id, the same one the Address DTO
        // exposes. Someone else's address and an unknown id are both a 404.
        $address = $id ? $this->find->get($user, $id) : null;
        abort_if($id && $address === null, 404);

        $this->addresses->save($user, $addressData, $address, $defaults);

        return back();
    }
}
