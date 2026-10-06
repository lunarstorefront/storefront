<?php

namespace Lunar\Storefront\Actions\Account;

use Lunar\Core\Contracts\Actions\Customers\UpdatesCustomer;
use Lunar\Core\Models\Customer;

/**
 * Wraps whichever update-customer action is bound (the panel's customer
 * edit uses it), re-deriving the customer's groups afterwards. With a
 * CustomerGroupResolver bound, groups follow its rule rather than a hand
 * pick, so they can't drift from the data the rule reads. A no-op without
 * one.
 */
class UpdateCustomerWithGroups implements UpdatesCustomer
{
    public function __construct(
        private readonly UpdatesCustomer $inner,
        private readonly SyncCustomerGroups $syncCustomerGroups,
    ) {}

    public function execute(Customer $customer, array $attributes, ?array $customerGroupIds = null): Customer
    {
        $customer = $this->inner->execute($customer, $attributes, $customerGroupIds);

        $this->syncCustomerGroups->sync($customer);

        return $customer;
    }
}
