<?php

namespace Lunar\Storefront\Managers;

use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Product;
use Lunar\Search\Data\SearchResults;
use Lunar\Storefront\Actions\Catalog\SearchProducts;

class SearchManager implements \Lunar\Storefront\Contracts\SearchManager
{
    public function getResults(
        ?string $query = null,
        ?Collection $collection = null,
        int $perPage = 40,
        ?string $sort = 'relevance:asc',
        array $filters = [],
    ): SearchResults {
        $facetables = collect(
            array_keys(config('lunar.search.facets.'.Product::class, [])),
        )
            ->mapWithKeys(
                fn ($facet) => [
                    $facet => request()->get(str_replace('.', '_', $facet)),
                ],
            )
            ->filter();

        $results = (new SearchProducts)->handle(
            query: $query,
            facets: $facetables->toArray(),
            collectionIds: collect([$collection?->id])
                ->filter()
                ->toArray(),
            filters: $filters,
            sort: $sort,
            perPage: $perPage,
        );

        if ($results->count && $query) {
            $this->updateQuerySuggestion($query);
        }

        return $results;
    }

    public function updateQuerySuggestion(string $term)
    {
        // Disabled — requires Meilisearch. TODO: implement for Typesense.
    }
}
