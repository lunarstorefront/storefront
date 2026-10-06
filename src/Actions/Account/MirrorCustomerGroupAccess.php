<?php

namespace Lunar\Storefront\Actions\Account;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Models\CustomerGroup;

class MirrorCustomerGroupAccess
{
    /**
     * Lunar's customer-group pivots that grant a group access to something,
     * keyed to the column naming that something. A customer with groups can
     * only buy products, see collections, use shipping methods and
     * discounts, and be taxed by zones attached to one of their groups.
     */
    private const PIVOTS = [
        'customer_group_product' => 'product_id',
        'collection_customer_group' => 'collection_id',
        'customer_group_shipping_method' => 'shipping_method_id',
        'customer_group_discount' => 'discount_id',
        'tax_zone_customer_groups' => 'tax_zone_id',
    ];

    /**
     * Give `$to` everything `$from` has: each pivot row of `$from` is copied
     * (flags and dates included) where `$to` has no row for the same record.
     * For a new group whose customers sit in it alone, such as trade beside
     * the default retail group; without it they would lose every product,
     * shipping method and tax zone only the old group was attached to.
     * Rows `$to` already has are left as they are, so it is safe to re-run.
     */
    public function mirror(CustomerGroup $from, CustomerGroup $to): void
    {
        if ($from->is($to)) {
            return;
        }

        foreach (self::PIVOTS as $pivot => $ownerKey) {
            $table = config('lunar.database.table_prefix').$pivot;

            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_diff(Schema::getColumnListing($table), ['id']));

            $select = array_map(
                fn (string $column) => $column === 'customer_group_id'
                    ? DB::raw((int) $to->getKey().' as customer_group_id')
                    : "source.{$column}",
                $columns,
            );

            DB::table($table)->insertUsing(
                $columns,
                DB::table("{$table} as source")
                    ->select($select)
                    ->where('source.customer_group_id', $from->getKey())
                    ->whereNotExists(fn (Builder $query) => $query
                        ->from("{$table} as target")
                        ->whereColumn("target.{$ownerKey}", "source.{$ownerKey}")
                        ->where('target.customer_group_id', $to->getKey())),
            );
        }
    }
}
