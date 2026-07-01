<?php

use Lunar\Core\Models\Collection;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Language;
use Lunar\Storefront\Actions\Catalog\GetCollectionBreadcrumbs;
use Lunar\Storefront\Data\Breadcrumb;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
});

test('it returns empty collection for null input', function () {
    $action = new GetCollectionBreadcrumbs;
    $breadcrumbs = $action->get(null);

    expect($breadcrumbs)->toBeEmpty();
});

test('it returns single breadcrumb for root collection', function () {
    $language = Language::getDefault();

    $collection = Collection::factory()->create(['status' => 'published', 
        'attribute_data' => collect([
            'name' => new Text('Test Collection'),
        ]),
    ]);

    $collection->urls()->create([
        'slug' => 'test-collection',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $collection->load('defaultUrl', 'ancestors.defaultUrl');

    $action = new GetCollectionBreadcrumbs;
    $breadcrumbs = $action->get($collection);

    expect($breadcrumbs)->toHaveCount(1)
        ->and($breadcrumbs->first())->toBeInstanceOf(Breadcrumb::class)
        ->and($breadcrumbs->first()->slug)->toBe('test-collection')
        ->and($breadcrumbs->first()->model)->toBe('collection');
});

test('it includes ancestors in breadcrumbs', function () {
    $language = Language::getDefault();

    $grandparent = Collection::factory()->create(['status' => 'published', 
        'attribute_data' => collect([
            'name' => new Text('Grandparent'),
        ]),
    ]);
    $grandparent->urls()->create([
        'slug' => 'grandparent',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $parent = Collection::factory()->create(['status' => 'published', 
        'collection_group_id' => $grandparent->collection_group_id,
        'attribute_data' => collect([
            'name' => new Text('Parent'),
        ]),
    ]);
    $parent->urls()->create([
        'slug' => 'parent',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $parent->appendToNode($grandparent)->save();

    $child = Collection::factory()->create(['status' => 'published', 
        'collection_group_id' => $grandparent->collection_group_id,
        'attribute_data' => collect([
            'name' => new Text('Child'),
        ]),
    ]);
    $child->urls()->create([
        'slug' => 'child',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $child->appendToNode($parent)->save();

    $child->load('defaultUrl', 'ancestors.defaultUrl');

    $action = new GetCollectionBreadcrumbs;
    $breadcrumbs = $action->get($child);

    expect($breadcrumbs)->toHaveCount(3);

    $slugs = $breadcrumbs->pluck('slug')->all();
    expect($slugs)->toBe(['grandparent', 'parent', 'child']);
});

test('breadcrumbs are in correct order from root to current', function () {
    $language = Language::getDefault();

    $root = Collection::factory()->create(['status' => 'published', 
        'attribute_data' => collect(['name' => new Text('Root')]),
    ]);
    $root->urls()->create([
        'slug' => 'root',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $level1 = Collection::factory()->create(['status' => 'published', 
        'collection_group_id' => $root->collection_group_id,
        'attribute_data' => collect(['name' => new Text('Level 1')]),
    ]);
    $level1->urls()->create([
        'slug' => 'level-1',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $level1->appendToNode($root)->save();

    $level2 = Collection::factory()->create(['status' => 'published', 
        'collection_group_id' => $root->collection_group_id,
        'attribute_data' => collect(['name' => new Text('Level 2')]),
    ]);
    $level2->urls()->create([
        'slug' => 'level-2',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $level2->appendToNode($level1)->save();

    $level2->load('defaultUrl', 'ancestors.defaultUrl');

    $action = new GetCollectionBreadcrumbs;
    $breadcrumbs = $action->get($level2);

    // Should be: Root -> Level 1 -> Level 2
    expect($breadcrumbs->first()->slug)->toBe('root')
        ->and($breadcrumbs->last()->slug)->toBe('level-2');
});

test('it preserves existing breadcrumbs when constructed with collection', function () {
    $language = Language::getDefault();

    $existing = collect([
        Breadcrumb::from(['label' => 'Home', 'model' => 'page', 'slug' => '/']),
    ]);

    $collection = Collection::factory()->create(['status' => 'published', 
        'attribute_data' => collect(['name' => new Text('Products')]),
    ]);
    $collection->urls()->create([
        'slug' => 'products',
        'default' => true,
        'language_id' => $language->id,
    ]);
    $collection->load('defaultUrl', 'ancestors.defaultUrl');

    $action = new GetCollectionBreadcrumbs($existing);
    $breadcrumbs = $action->get($collection);

    expect($breadcrumbs)->toHaveCount(2)
        ->and($breadcrumbs->first()->slug)->toBe('/')
        ->and($breadcrumbs->last()->slug)->toBe('products');
});
