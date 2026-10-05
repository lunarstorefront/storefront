<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Address;

class DeleteCustomerAddress
{
    public function __construct(
        protected GetCustomerAddresses $addresses = new GetCustomerAddresses,
        protected SetDefaultCustomerAddress $defaults = new SetDefaultCustomerAddress,
    ) {}

    /**
     * Remove an address from the user's address book. When it was a default,
     * the newest remaining address takes over, so the checkout always has one
     * to prefill while any are saved. Orders keep their own copy of the
     * address, so past orders are unaffected.
     */
    public function delete(Model&LunarUser $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address) {
            $wasDefault = array_filter(
                SetDefaultCustomerAddress::TYPES,
                fn (string $type): bool => (bool) $address->getAttribute($type.'_default'),
            );

            $address->delete();

            $successor = $this->addresses->query($user)->latest('id')->first();

            if ($successor === null) {
                return;
            }

            foreach ($wasDefault as $type) {
                $this->defaults->set($user, $successor, $type);
            }
        });
    }
}
