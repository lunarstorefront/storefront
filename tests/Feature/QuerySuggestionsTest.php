<?php

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\Url;
use Lunar\Search\Data\SearchHit;
use Lunar\Search\Data\SearchResults;
use Lunar\Search\Engines\AbstractEngine;
use Lunar\Search\Facades\Search;
use Lunar\Storefront\RouteRegistrar;
use Meilisearch\Endpoints\Indexes;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();

    RouteRegistrar::register(['suggestions']);
    Route::getRoutes()->refreshNameLookups();
});

/**
 * A published product with a default URL.
 */
function suggestedProduct(string $name, string $status = 'published'): Product
{
    $product = Product::factory()->for(test()->productType)->create([
        'status' => $status,
        'name' => collect(['en' => $name]),
    ]);

    Url::factory()->create([
        'element_type' => $product->getMorphClass(),
        'element_id' => $product->id,
        'default' => true,
        'slug' => str($name)->slug()->toString(),
    ]);

    return $product;
}

/**
 * Swap the product search engine for a fake returning the given ids as hits.
 * Like the real manager, each driver resolution builds a new engine; every
 * engine reports into the returned recorder.
 *
 * @param  list<int|string>  $ids
 */
function fakeSuggestionsEngine(array $ids = []): object
{
    $recorder = new class
    {
        /** @var list<array{query: string, filters: array<string, mixed>, perPage: int}> */
        public array $calls = [];
    };

    Search::extend('suggestions-fake', fn () => new class($recorder, $ids) extends AbstractEngine
    {
        public function __construct(private object $recorder, private array $ids) {}

        protected function getFieldConfig(): array
        {
            return [];
        }

        public function get(): SearchResults
        {
            $this->recorder->calls[] = [
                'query' => $this->getQuery(),
                'filters' => $this->getFilters(),
                'perPage' => $this->perPage,
            ];

            return new SearchResults(
                query: $this->getQuery(),
                count: count($this->ids),
                page: 1,
                perPage: $this->perPage,
                totalPages: 1,
                hits: array_map(
                    fn ($id): SearchHit => SearchHit::from(['highlights' => [], 'document' => ['id' => (string) $id]]),
                    $this->ids,
                ),
                facets: [],
                links: Mockery::mock(View::class),
            );
        }
    });
    config(['lunar.search.engine_map' => [Product::class => 'suggestions-fake']]);

    return $recorder;
}

it('returns product suggestions from the search engine', function () {
    $infinite = suggestedProduct('Halo Infinite');
    $three = suggestedProduct('Halo 3');
    $engine = fakeSuggestionsEngine([$infinite->id, $three->id]);

    $response = $this->getJson('/api/query-suggestions?term=halo')
        ->assertOk()
        ->assertJsonCount(2);

    expect($response->json('0.term'))->toBe('Halo Infinite')
        ->and($response->json('0.product.url.slug'))->toBe('halo-infinite')
        ->and($response->json('1.term'))->toBe('Halo 3')
        ->and($engine->calls)->toBe([[
            'query' => 'halo',
            'filters' => ['status' => '=`published`'],
            'perPage' => 6,
        ]]);
});

it('short terms return an empty list without searching', function (string $term) {
    $engine = fakeSuggestionsEngine();

    $this->getJson('/api/query-suggestions?term='.$term)
        ->assertOk()
        ->assertExactJson([]);

    expect($engine->calls)->toBe([]);
})->with(['one character' => 'h', 'blank' => '', 'padded' => '%20h%20']);

it('suggestions skip hits with no published product', function () {
    $published = suggestedProduct('Halo Infinite');
    $draft = suggestedProduct('Halo Draft', 'draft');
    fakeSuggestionsEngine([$published->id, $draft->id, 999999]);

    $this->getJson('/api/query-suggestions?term=halo')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.term', 'Halo Infinite');
});

it('suggestions ignore filters set earlier in the request', function () {
    $engine = fakeSuggestionsEngine([]);

    Search::model(Product::class)->filter(['brand' => 'x']);

    $this->getJson('/api/query-suggestions?term=halo')->assertOk();

    expect($engine->calls)->toHaveCount(1)
        ->and($engine->calls[0]['filters'])->not->toHaveKey('brand');
});

it('meilisearch driver keeps the existing behaviour', function () {
    config(['storefront.search.suggestions.driver' => 'meilisearch']);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('search')
        ->once()
        ->with('halo', ['sort' => ['count:desc']])
        ->andReturn(new class
        {
            public function getHits(): array
            {
                return [['term' => 'halo infinite']];
            }
        });

    $engine = Mockery::mock(MeilisearchEngine::class);
    $engine->shouldReceive('createIndex')->once()->with('storefront_query_suggestions')->andReturn($index);

    $this->mock(EngineManager::class)->shouldReceive('engine')->with('meilisearch')->andReturn($engine);

    $this->getJson('/api/query-suggestions?term=halo')
        ->assertOk()
        ->assertExactJson([['term' => 'halo infinite', 'product' => null]]);
});
