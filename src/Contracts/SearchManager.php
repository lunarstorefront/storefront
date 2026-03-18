<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Catalog\Models\Collection;
use Lunar\Search\DataObjects\SearchResults;

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
