<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Address;

class FindCustomerAddress
{
    public function __construct(protected GetCustomerAddresses $addresses = new GetCustomerAddresses) {}

    /**
     * One of the user's addresses by its public id, or null when it is not
     * theirs, so a caller can 404 without revealing that it exists.
     */
    public function get(Model&LunarUser $user, string $publicId): ?Address
    {
        return $this->addresses->query($user)->where('public_id', $publicId)->first();
    }
}
