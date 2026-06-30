<?php

use Lunar\Catalog\Models\Collection;
use Lunar\Core\Models\Language;
use Lunar\Storefront\Actions\Catalog\GetCollectionBySlug;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
});

test('it returns collection by slug', function () {
    $collection = Collection::factory()->create();
    $language = Language::getDefault();

    $collection->urls()->create([
        'slug' => 'test-collection',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetCollectionBySlug();
    $found = $action->get('test-collection');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($collection->id);
});

test('it returns null for non-existent slug', function () {
    $action = new GetCollectionBySlug();
    $found = $action->get('non-existent');

    expect($found)->toBeNull();
});

test('it only matches default urls', function () {
    $collection = Collection::factory()->create();
    $language = Language::getDefault();

    // Create non-default URL
    $collection->urls()->create([
        'slug' => 'non-default-slug',
        'default' => false,
        'language_id' => $language->id,
    ]);

    // Create default URL
    $collection->urls()->create([
        'slug' => 'default-slug',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetCollectionBySlug();

    expect($action->get('non-default-slug'))->toBeNull()
        ->and($action->get('default-slug'))->not->toBeNull();
});

test('it only matches root collections when no child specified', function () {
    $language = Language::getDefault();

    $parent = Collection::factory()->create();
    $parent->urls()->create([
        'slug' => 'parent',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child = Collection::factory()->create([
        'collection_group_id' => $parent->collection_group_id,
    ]);
    $child->urls()->create([
        'slug' => 'child',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $child->appendToNode($parent)->save();

    $action = new GetCollectionBySlug();

    // Root collection should be found
    expect($action->get('parent'))->not->toBeNull();

    // Child collection should NOT be found when accessed as root
    expect($action->get('child'))->toBeNull();
});

test('it can find child collection by parent and child slug', function () {
    $language = Language::getDefault();

    $parent = Collection::factory()->create();
    $parent->urls()->create([
        'slug' => 'parent',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child = Collection::factory()->create([
        'collection_group_id' => $parent->collection_group_id,
    ]);
    $child->urls()->create([
        'slug' => 'child',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $child->appendToNode($parent)->save();

    $action = new GetCollectionBySlug();
    $found = $action->get('parent', 'child');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($child->id);
});

test('it returns null when child does not belong to parent', function () {
    $language = Language::getDefault();

    $parent1 = Collection::factory()->create();
    $parent1->urls()->create([
        'slug' => 'parent1',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $parent2 = Collection::factory()->create();
    $parent2->urls()->create([
        'slug' => 'parent2',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child = Collection::factory()->create([
        'collection_group_id' => $parent2->collection_group_id,
    ]);
    $child->urls()->create([
        'slug' => 'child',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $child->appendToNode($parent2)->save();

    $action = new GetCollectionBySlug();

    // Child belongs to parent2, not parent1
    expect($action->get('parent1', 'child'))->toBeNull();
});

test('it eager loads specified relations', function () {
    $collection = Collection::factory()->create();
    $language = Language::getDefault();

    $collection->urls()->create([
        'slug' => 'test',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $action = new GetCollectionBySlug();
    $found = $action->get('test', null, ['defaultUrl', 'children']);

    expect($found->relationLoaded('defaultUrl'))->toBeTrue()
        ->and($found->relationLoaded('children'))->toBeTrue();
});
