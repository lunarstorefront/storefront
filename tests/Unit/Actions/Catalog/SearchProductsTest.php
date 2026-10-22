<?php

use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\Region;
use Lunar\Search\Data\SearchResults;
use Lunar\Search\Facades\Search;
use Lunar\Storefront\Actions\Catalog\SearchProducts;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);
});

/**
 * Note: SearchProducts relies on the Lunar Search package and Meilisearch.
 * Full integration tests would require a running Meilisearch instance.
 * These tests verify the action class structure and parameter handling.
 */
test('it can be instantiated', function () {
    $action = new SearchProducts;

    expect($action)->toBeInstanceOf(SearchProducts::class);
});

test('it has handle method with correct signature', function () {
    $action = new SearchProducts;

    $reflection = new ReflectionMethod($action, 'handle');
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(6);

    // Check parameter names and defaults
    expect($parameters[0]->getName())->toBe('query')
        ->and($parameters[0]->isOptional())->toBeTrue()
        ->and($parameters[0]->getDefaultValue())->toBe('');

    expect($parameters[1]->getName())->toBe('facets')
        ->and($parameters[1]->isOptional())->toBeTrue();

    expect($parameters[2]->getName())->toBe('collectionIds')
        ->and($parameters[2]->isOptional())->toBeTrue();

    expect($parameters[3]->getName())->toBe('filters')
        ->and($parameters[3]->isOptional())->toBeTrue();

    expect($parameters[4]->getName())->toBe('sort')
        ->and($parameters[4]->isOptional())->toBeTrue();

    expect($parameters[5]->getName())->toBe('perPage')
        ->and($parameters[5]->isOptional())->toBeTrue()
        ->and($parameters[5]->getDefaultValue())->toBe(40);
});

test('sort direction validation accepts only asc and desc', function () {
    // This tests the internal logic by examining the code
    // The action validates sort direction and defaults to 'asc' if invalid
    $action = new SearchProducts;

    $reflection = new ReflectionClass($action);
    $source = file_get_contents($reflection->getFileName());

    // Verify the validation logic exists
    expect($source)->toContain("if (! in_array(\$sortDir, ['asc', 'desc']))")
        ->and($source)->toContain("\$sortDir = 'asc'");
});

test('it only searches published products', function (array $filters) {
    Search::shouldReceive('model')->with(Product::class)->andReturnSelf();
    Search::shouldReceive('perPage', 'query')->andReturnSelf();
    Search::shouldReceive('filter')->once()->with(Mockery::capture($sent))->andReturnSelf();
    Search::shouldReceive('get')->andReturn(Mockery::mock(SearchResults::class));

    (new SearchProducts)->handle(filters: $filters);

    expect($sent['status'])->toBe('=`published`')
        ->and($sent)->toMatchArray(array_diff_key($filters, ['status' => true]));
})->with([
    'no filters' => [[]],
    'other filters kept' => [['availability' => '=`In stock`']],
    // A caller can't widen a public search to drafts.
    'caller status replaced' => [['status' => ':=draft']],
]);
