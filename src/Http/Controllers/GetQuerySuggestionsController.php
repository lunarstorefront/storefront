<?php

namespace Lunar\Storefront\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Scout\Scout;
use Lunar\Core\Models\Product;
use Lunar\Search\Facades\Search;
use Lunar\Storefront\Data\Product as ProductData;
use Lunar\Storefront\Data\SearchQueryHit;

class GetQuerySuggestionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('term'));

        if (mb_strlen($term) < config('storefront.search.suggestions.min_length', 2)) {
            return response()->json([]);
        }

        if (config('storefront.search.suggestions.driver', 'search') === 'meilisearch') {
            return $this->meilisearchSuggestions($term);
        }

        return response()->json($this->productSuggestions($term));
    }

    protected function meilisearchSuggestions(string $term): JsonResponse
    {
        $results = Scout::engine('meilisearch')->createIndex('storefront_query_suggestions')
            ->search($term, ['sort' => ['count:desc']]);

        return response()->json(
            collect(
                $results->getHits()
            )->map(
                fn (array $hit) => new SearchQueryHit(term: $hit['term'])
            )
        );
    }

    /**
     * @return array<int, SearchQueryHit>
     */
    protected function productSuggestions(string $term): array
    {
        // The manager caches one engine per request; start from a fresh one so
        // filters or facets set earlier don't leak into this query.
        Search::forgetDrivers();

        $results = Search::model(Product::class)
            ->query($term)
            ->perPage(config('storefront.search.suggestions.limit', 6))
            ->filter(['status' => '=`published`'])
            ->get();

        $ids = collect($results->hits)->map(fn ($hit) => $hit->document['id'] ?? null)->filter()->all();

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->with(['defaultUrl', 'thumbnail'])
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->map(fn (Product $product) => ProductData::from($product))
            ->map(fn (ProductData $product) => new SearchQueryHit(term: $product->name, product: $product))
            ->values()
            ->all();
    }
}
