<?php

namespace Lunar\Storefront\Search;

use Illuminate\Http\Request;

final class PerPage
{
    /** The page size when none is requested or the request is not an offered option. */
    public static function default(): int
    {
        return (int) config('storefront.search.per_page.default', 40);
    }

    /**
     * The page sizes the listing toolbar offers.
     *
     * @return list<int>
     */
    public static function options(): array
    {
        return array_values(array_map('intval', (array) config('storefront.search.per_page.options', [40, 80, 120])));
    }

    /**
     * The requested page size when it is one the toolbar offers, otherwise
     * the default, so a hand-edited ?per_page= can't ask for thousands.
     */
    public static function fromRequest(Request $request): int
    {
        $requested = $request->integer('per_page', self::default());

        return in_array($requested, self::options(), true) ? $requested : self::default();
    }
}
