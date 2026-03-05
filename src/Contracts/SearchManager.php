<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\Collection;
use Lunar\Search\Data\SearchResults;

interface SearchManager
{
    public function getResults(
        ?string $query = null,
        ?Collection $collection = null,
        int $perPage = 40,
        ?string $sort = 'relevance:asc',
        array $filters = [],
    ): SearchResults;
}
