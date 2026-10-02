<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Address;

class SaveCustomerAddress
{
    public function __construct(
        protected GetCustomerForUser $customers = new GetCustomerForUser,
        protected GetCustomerAddresses $addresses = new GetCustomerAddresses,
        protected SetDefaultCustomerAddress $defaults = new SetDefaultCustomerAddress,
    ) {}

    /**
     * Add an address to the user's address book, or update one of theirs.
     * Default flags in the attributes are ignored: pass the types to make it
     * the default for in $defaults, so a save can never leave two defaults or
     * none. The first address a user saves is their default for both.
     *
     * @param  array<string, mixed>  $attributes  Lunar address columns
     * @param  list<'shipping'|'billing'>  $defaults
     */
    public function save(Model&LunarUser $user, array $attributes, ?Address $address = null, array $defaults = []): Address
    {
        $attributes = Arr::except($attributes, ['shipping_default', 'billing_default', 'customer_id']);

        return DB::transaction(function () use ($user, $attributes, $address, $defaults) {
            if ($address === null) {
                $isFirst = ! $this->addresses->query($user)->exists();

                /** @var Address $address */
                $address = $this->customers->get($user)->addresses()->create($attributes);

                if ($isFirst) {
                    $defaults = SetDefaultCustomerAddress::TYPES;
                }
            } else {
                $address->fill($attributes)->save();
            }

            foreach (array_unique($defaults) as $type) {
                $this->defaults->set($user, $address, $type);
            }

            return $address->refresh();
        });
    }
}
