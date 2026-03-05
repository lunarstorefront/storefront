<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Str;
use Laravel\Scout\Scout;
use Lunar\Models\Contracts\Collection;
use Lunar\Models\Product;
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

        $results = new SearchProducts()->handle(
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
        $index = Scout::engine('meilisearch')->createIndex(
            'storefront_query_suggestions',
        );

        $term = trim(mb_strtolower($term));
        $termId = Str::slug($term);

        try {
            $doc = $index->getDocument($termId); // exact match by primary key
            $index->updateDocuments([
                [
                    'signature' => $termId,
                    'term' => $term,
                    'count' => ((int) ($doc['count'] ?? 0)) + 1,
                ],
            ]);
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // If it doesn't exist, Meilisearch returns 404
            if (($e->httpStatus ?? null) === 404) {
                $index->addDocuments([
                    [
                        'signature' => $termId,
                        'term' => $term,
                        'count' => 1,
                    ],
                ]);
            } else {
                throw $e;
            }
        }
    }
}
