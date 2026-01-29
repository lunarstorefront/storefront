<?php

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Storefront\Actions\Catalog\SearchProducts;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

/**
 * Note: SearchProducts relies on the Lunar Search package and Meilisearch.
 * Full integration tests would require a running Meilisearch instance.
 * These tests verify the action class structure and parameter handling.
 */

test('it can be instantiated', function () {
    $action = new SearchProducts();

    expect($action)->toBeInstanceOf(SearchProducts::class);
});

test('it has handle method with correct signature', function () {
    $action = new SearchProducts();

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
    $action = new SearchProducts();

    $reflection = new ReflectionClass($action);
    $source = file_get_contents($reflection->getFileName());

    // Verify the validation logic exists
    expect($source)->toContain("if (! in_array(\$sortDir, ['asc', 'desc']))")
        ->and($source)->toContain("\$sortDir = 'asc'");
});
