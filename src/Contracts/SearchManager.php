<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Core\Models\Collection;
use Lunar\Search\Data\SearchResults;

interface SearchManager
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getResults(
        ?string $query = null,
        ?Collection $collection = null,
        int $perPage = 40,
        ?string $sort = 'relevance:asc',
        array $filters = [],
    ): SearchResults;
}
