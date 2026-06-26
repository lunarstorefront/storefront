<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Catalog\Models\Product;
use Lunar\Search\DataObjects\SearchResults;
use Lunar\Search\Facades\Search;

class SearchProducts
{
    /**
     * @param  array<array-key, mixed>|null  $facets
     * @param  array<int, int|string>  $collectionIds
     * @param  array<string, mixed>  $filters
     */
    public function handle(?string $query = '', ?array $facets = [], array $collectionIds = [], array $filters = [], ?string $sort = null, int $perPage = 40): SearchResults
    {
        if (! blank($collectionIds)) {
            $filters['collection_ids'] = $collectionIds;
        }

        $products = Search::model(Product::class)
            ->perPage($perPage)
            ->filter($filters)
            ->query($query ?: '');

        $sorting = explode(':', $sort ?? '');

        $sortDir = $sorting[1] ?? 'asc';
        $sortField = $sorting[0] ?? '';

        if (! in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        if ($sortField) {
            $products->sort("{$sortField}:{$sortDir}");
        }

        if (! blank($facets)) {
            $products->setFacets(
                array_map(function ($facet) {
                    return is_array($facet) ? $facet : [$facet];
                }, $facets)
            );
        }

        return $products->search();
    }
}
