<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Order;

class FindCustomerOrder
{
    public function __construct(protected GetCustomerOrders $orders = new GetCustomerOrders) {}

    /**
     * One of the user's placed orders by its reference, or null when it is
     * not theirs, so a caller can 404 without revealing that it exists.
     *
     * @param  list<string>|array<string, \Closure>  $with
     */
    public function get(Model&LunarUser $user, string $reference, array $with = []): ?Order
    {
        return $this->orders->query($user)
            ->where('reference', $reference)
            ->with($with)
            ->first();
    }
}
