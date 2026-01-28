<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript  */
class SearchQueryHit extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $term,
    ) {

    }
}
