<?php

namespace Lunar\Storefront\Actions\Account;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Order;
use Lunar\Storefront\Data\Order as OrderData;

class GetCustomerOrders
{
    /**
     * Everything an order history card renders, so a page of cards never
     * lazy loads: lines with their images, the shipping line (delivery or
     * collection) and the delivery address.
     */
    public const LIST_RELATIONS = [
        'physicalLines.purchasable.images',
        'physicalLines.purchasable.product.thumbnail',
        'shippingLines',
        'shippingAddress.country',
    ];

    /**
     * A page of the user's placed orders, newest first.
     *
     * `search` matches the order number, the customer's own reference, or a
     * line's SKU or product name. `from` and `to` bound the placed date.
     * `sort` is `newest` (default), `oldest` or `total` (highest first).
     * `$scope` lets the caller narrow further, such as a status tab, since
     * how statuses are grouped and worded belongs to the storefront.
     * `$transform` shapes each order with the model in hand (the default is
     * the Order DTO), for cards that need more than the DTO carries.
     *
     * @template TItem
     *
     * @param  array{search?: string|null, from?: string|null, to?: string|null, sort?: string|null}  $filters
     * @param  (Closure(Builder<Order>): mixed)|null  $scope
     * @param  (Closure(Order): TItem)|null  $transform
     * @return LengthAwarePaginator<int, TItem|OrderData>
     */
    public function get(Model&LunarUser $user, array $filters = [], int $perPage = 10, ?Closure $scope = null, ?Closure $transform = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->query($user)
            ->with(self::LIST_RELATIONS)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';

                $query->whereRaw('lower(reference) like ?', [$like])
                    ->orWhereRaw('lower(customer_reference) like ?', [$like])
                    ->orWhereHas('lines', fn (Builder $lines) => $lines
                        ->whereRaw('lower(identifier) like ?', [$like])
                        ->orWhereRaw('lower(description) like ?', [$like]));
            }))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('placed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('placed_at', '<=', $to))
            ->when($scope !== null, fn (Builder $query) => $scope($query))
            ->tap(fn (Builder $query) => match ($filters['sort'] ?? null) {
                'oldest' => $query->oldest('placed_at'),
                'total' => $query->orderByDesc('total')->latest('placed_at'),
                default => $query->latest('placed_at'),
            })
            ->paginate($perPage)
            ->withQueryString()
            ->through($transform ?? fn (Order $order): OrderData => OrderData::fromModel($order));
    }

    /**
     * The user's placed orders: their own, plus any placed under a customer
     * account they belong to (a company with several buyers). Guest orders
     * on the same email are not included.
     *
     * @return Builder<Order>
     */
    public function query(Model&LunarUser $user): Builder
    {
        $customerIds = $user->customers()->pluck($user->customers()->getRelated()->getQualifiedKeyName());

        return Order::query()
            ->whereNotNull('placed_at')
            ->where(fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->when($customerIds->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('customer_id', $customerIds)));
    }
}
