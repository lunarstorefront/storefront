<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Core\Models\Customer;

/**
 * Decides which customer groups a customer belongs in, from the customer's
 * own data (an approval status, an account number, ...).
 *
 * Opt-in: nothing is bound by default and customer groups are then left to
 * Lunar and staff. Binding an implementation turns on SyncCustomerGroups,
 * which rewrites a customer's groups from this resolver whenever they are
 * saved or created/updated through Lunar's customer actions. Because Lunar
 * prices carts and products, and limits what can be bought, by customer
 * group, this is how a storefront puts rules like "approved trade accounts
 * get trade pricing" in one place.
 */
interface CustomerGroupResolver
{
    /**
     * The handles of the groups the customer should be in. An empty list, or
     * handles that do not exist, leave the customer in the default group.
     *
     * @return list<string>
     */
    public function groupsFor(Customer $customer): array;
}
