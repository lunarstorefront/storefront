<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\Collection;
use Lunar\Search\Data\SearchResults;

interface SearchManager
{
    public function getResults(?string $term = null, ?Collection $collection = null): SearchResults;
}
