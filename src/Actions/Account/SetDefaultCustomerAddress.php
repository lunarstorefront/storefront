<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Address;

class SetDefaultCustomerAddress
{
    public const array TYPES = ['shipping', 'billing'];

    public function __construct(protected GetCustomerAddresses $addresses = new GetCustomerAddresses) {}

    /**
     * Make the address the user's default for delivery (`shipping`) or
     * billing, clearing it from their other addresses so exactly one default
     * of each type remains. The address must already be one of theirs.
     */
    public function set(Model&LunarUser $user, Address $address, string $type): Address
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown default address type [{$type}].");
        }

        $column = $type.'_default';

        DB::transaction(function () use ($user, $address, $column) {
            $this->addresses->query($user)
                ->whereKeyNot($address->getKey())
                ->where($column, true)
                ->update([$column => false]);

            $address->forceFill([$column => true])->save();
        });

        return $address;
    }
}
