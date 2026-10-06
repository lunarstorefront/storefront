<?php

namespace Lunar\Storefront\Actions\Account;

use Lunar\Core\Contracts\Actions\Customers\CreatesCustomer;
use Lunar\Core\Models\Customer;

/**
 * Wraps whichever create-customer action is bound, re-deriving the new
 * customer's groups afterwards. Lunar's action syncs the groups it is given
 * after saving the customer, which would otherwise overwrite the groups
 * SyncCustomerGroups set on save. A no-op without a CustomerGroupResolver.
 */
class CreateCustomerWithGroups implements CreatesCustomer
{
    public function __construct(
        private readonly CreatesCustomer $inner,
        private readonly SyncCustomerGroups $syncCustomerGroups,
    ) {}

    public function execute(array $attributes, array $customerGroupIds = []): Customer
    {
        $customer = $this->inner->execute($attributes, $customerGroupIds);

        $this->syncCustomerGroups->sync($customer);

        return $customer;
    }
}
