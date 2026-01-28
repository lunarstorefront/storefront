<?php

namespace Lunar\Storefront\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Scout\Scout;
use Lunar\Storefront\Data\SearchQueryHit;

class GetQuerySuggestionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->get('term');

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
}