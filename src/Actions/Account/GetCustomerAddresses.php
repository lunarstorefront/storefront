<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Address;
use Lunar\Core\Models\Customer;

class GetCustomerAddresses
{
    /**
     * Addresses saved against any of the user's customers.
     *
     * @return Builder<Address>
     */
    public function query(Model&LunarUser $user): Builder
    {
        return Address::query()->whereIn('customer_id', $user->customers()->select((new Customer)->getQualifiedKeyName()));
    }

    /**
     * The user's address book: the default delivery address first, then the
     * default billing address, then newest first.
     *
     * @return Collection<int, Address>
     */
    public function get(Model&LunarUser $user): Collection
    {
        return $this->query($user)
            ->with('country')
            ->orderByDesc('shipping_default')
            ->orderByDesc('billing_default')
            ->orderByDesc('id')
            ->get();
    }
}
