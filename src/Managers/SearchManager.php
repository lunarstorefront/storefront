<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Product;
use Lunar\Search\Data\SearchResults;
use Lunar\Storefront\Actions\Catalog\SearchProducts;

class SearchManager implements \Lunar\Storefront\Contracts\SearchManager
{
    /**
     * Most values one facet may filter on in a single request.
     */
    public const MAX_FACET_VALUES = 50;

    /**
     * Longest facet value accepted; anything longer cannot be a real value.
     */
    public const MAX_FACET_VALUE_LENGTH = 255;

    public function getResults(
        ?string $query = null,
        ?Collection $collection = null,
        int $perPage = 40,
        ?string $sort = 'relevance:asc',
        array $filters = [],
    ): SearchResults {
        $results = (new SearchProducts)->handle(
            query: $query,
            facets: $this->requestedFacets(),
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

    /**
     * The configured facets the request filters on, each as a flat list of
     * strings.
     *
     * The values go straight into the engine's filter, so the request's shape
     * is not trusted. A facet is a single value or a flat list of them:
     * `?brand[][]=x` arrives as a nested array, which used to reach the engine
     * and fail with "Array to string conversion", and is now rejected with a
     * validation error. Empty and overlong values are dropped and the count is
     * capped, so one request cannot build an arbitrarily large filter.
     *
     * @return array<string, list<string>>
     *
     * @throws ValidationException
     */
    protected function requestedFacets(): array
    {
        return collect(array_keys(config('lunar.search.facets.'.Product::class, [])))
            ->mapWithKeys(function (string $facet) {
                $parameter = str_replace('.', '_', $facet);
                $values = Arr::wrap(request()->get($parameter));

                if (collect($values)->contains(fn ($value) => ! is_scalar($value) && $value !== null)) {
                    throw ValidationException::withMessages([
                        $parameter => "The {$parameter} filter must be a value or a list of values.",
                    ]);
                }

                return [
                    $facet => collect($values)
                        ->filter(fn ($value) => is_scalar($value))
                        ->map(fn ($value) => (string) $value)
                        ->filter(fn (string $value) => $value !== '' && mb_strlen($value) <= self::MAX_FACET_VALUE_LENGTH)
                        ->take(self::MAX_FACET_VALUES)
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->all();
    }

    public function updateQuerySuggestion(string $term)
    {
        // Disabled — requires Meilisearch. TODO: implement for Typesense.
    }
}
