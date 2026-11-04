<?php

namespace Lunar\Storefront\Search;

use Illuminate\Http\Request;

final class PriceFilter
{
    /**
     * A search range filter on the stored minor-unit price from the query
     * string bounds (in pounds). Null when neither bound is given.
     *
     * VAT-aware: the shopper types prices the way the site shows them (inc VAT
     * when the VAT key is `inc`), and the bounds are moved onto the basis Lunar
     * stores prices in (`lunar.pricing.stored_inclusive_of_tax`) at the
     * standard rate.
     * A product on a reduced or zero rate can sit slightly either side of an
     * inc-VAT bound; the standard rate covers nearly the whole catalogue.
     */
    public static function fromRequest(Request $request): ?string
    {
        $minKey = config('storefront.search.price_filter.min_key', 'price_min');
        $maxKey = config('storefront.search.price_filter.max_key', 'price_max');
        $vatKey = config('storefront.search.price_filter.vat_key', 'price_vat');
        $vatRate = (float) config('storefront.search.price_filter.vat_rate', 20);

        $shownIncVat = $request->query($vatKey) === 'inc';
        $storedIncVat = (bool) config('lunar.pricing.stored_inclusive_of_tax', false);
        $divisor = match (true) {
            $shownIncVat && ! $storedIncVat => 1 + ($vatRate / 100),
            ! $shownIncVat && $storedIncVat => 1 / (1 + ($vatRate / 100)),
            default => 1,
        };

        $bound = fn (string $key): ?int => $request->filled($key) && is_numeric($request->query($key))
            ? max(0, (int) round(((float) $request->query($key)) / $divisor * 100))
            : null;

        $min = $bound($minKey);
        $max = $bound($maxKey);

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            return sprintf('[%d..%d]', min($min, $max), max($min, $max));
        }

        return $min !== null ? ">={$min}" : "<={$max}";
    }
}
