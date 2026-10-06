<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Storefront\Contracts\CustomerGroupResolver;

class SyncCustomerGroups
{
    public function __construct(private readonly Container $container) {}

    /**
     * Put the customer in exactly the groups the bound CustomerGroupResolver
     * names, replacing any others. Does nothing when no resolver is bound.
     *
     * Handles that don't exist are logged and skipped; if none of the named
     * groups exist the customer falls back to the default group, so they are
     * never left priced from no group at all.
     */
    public function sync(Customer $customer): void
    {
        if (! $this->container->bound(CustomerGroupResolver::class)) {
            return;
        }

        $handles = array_values(array_unique($this->container->make(CustomerGroupResolver::class)->groupsFor($customer)));
        $groups = CustomerGroup::query()->whereIn('handle', $handles)->get();

        $missing = array_diff($handles, $groups->pluck('handle')->all());

        if ($missing !== []) {
            Log::error('Customer group resolver named groups that do not exist.', [
                'customer_id' => $customer->getKey(),
                'handles' => array_values($missing),
            ]);
        }

        if ($groups->isEmpty()) {
            $default = CustomerGroup::getDefault();

            if ($default === null) {
                return;
            }

            $groups->push($default);
        }

        $customer->customerGroups()->sync($groups->modelKeys());
    }
}
