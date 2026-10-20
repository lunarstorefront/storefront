<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Storefront\Contracts\SearchManager as SearchManagerContract;
use Lunar\Storefront\Managers\SearchManager;

beforeEach(function () {
    $this->manager = new SearchManager;

    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it implements the search manager contract', function () {
    expect($this->manager)->toBeInstanceOf(SearchManagerContract::class);
});

test('it has a getResults method', function () {
    expect(method_exists($this->manager, 'getResults'))->toBeTrue();
});

test('it has an updateQuerySuggestion method', function () {
    expect(method_exists($this->manager, 'updateQuerySuggestion'))->toBeTrue();
});

/**
 * Bind a request with the given query string and return a manager exposing
 * the facets it reads from it.
 */
function facetsFromQuery(string $query): array
{
    config()->set('lunar.search.facets.'.Product::class, [
        'brand' => [],
        'in_stock' => [],
        'category.level' => [],
    ]);

    app()->instance('request', Request::create('/search?'.$query));

    $manager = new class extends SearchManager
    {
        public function exposedRequestedFacets(): array
        {
            return $this->requestedFacets();
        }
    };

    return $manager->exposedRequestedFacets();
}

test('it reads configured facets from the request as flat lists of strings', function () {
    expect(facetsFromQuery('brand=Hager&in_stock=true&category_level[]=Cable&colour=Red'))->toBe([
        'brand' => ['Hager'],
        'in_stock' => ['true'],
        'category.level' => ['Cable'],
    ]);
});

test('it rejects nested facet arrays with a validation error', function (string $query, string $parameter) {
    expect(fn () => facetsFromQuery($query))->toThrow(
        fn (ValidationException $e) => expect($e->errors())->toHaveKey($parameter)
    );
})->with([
    'nested list' => ['brand[][]=Hager', 'brand'],
    'keyed nesting' => ['brand[x][y][]=Wago', 'brand'],
    'dotted facet' => ['category_level[][]=Cable', 'category_level'],
]);

test('it drops empty facet values', function () {
    expect(facetsFromQuery('brand[]=&brand[]=Hager&in_stock[]='))->toBe([
        'brand' => ['Hager'],
    ]);
});

test('it caps how many values and how long a value one facet may carry', function () {
    $values = collect(range(1, SearchManager::MAX_FACET_VALUES + 10))
        ->map(fn (int $i) => 'brand[]=b'.$i)
        ->push('brand[]='.str_repeat('x', SearchManager::MAX_FACET_VALUE_LENGTH + 1))
        ->join('&');

    $brands = facetsFromQuery($values)['brand'];

    expect($brands)->toHaveCount(SearchManager::MAX_FACET_VALUES)
        ->and($brands[0])->toBe('b1')
        ->and(collect($brands)->max(fn (string $brand) => strlen($brand)))->toBeLessThanOrEqual(SearchManager::MAX_FACET_VALUE_LENGTH);
});
