<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Core\Contracts\Actions\Customers\CreatesCustomer;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;

class GetCustomerForUser
{
    /**
     * The Lunar customer a user's account data (addresses and so on) hangs
     * off: their latest one, or a new one named after them in the default
     * customer group when they have none yet.
     */
    public function get(Model&LunarUser $user): Customer
    {
        if ($customer = $user->latestCustomer()) {
            return $customer;
        }

        $name = trim((string) $user->getAttribute('name'));
        $defaultGroup = CustomerGroup::getDefault();

        $customer = app(CreatesCustomer::class)->execute([
            'first_name' => Str::before($name, ' '),
            'last_name' => Str::contains($name, ' ') ? Str::after($name, ' ') : '',
        ], $defaultGroup ? [$defaultGroup->getKey()] : []);

        $user->customers()->attach($customer);

        return $customer;
    }
}
