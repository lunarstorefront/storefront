<?php

use Lunar\Catalog\Models\Collection;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Region;
use Lunar\Storefront\Data\Breadcrumb;
use Lunar\Storefront\Managers\CollectionManager;

beforeEach(function () {
    $this->manager = new CollectionManager;

    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);
});

test('it can get collection by slug', function () {
    $collection = Collection::factory()->create();

    $collection->urls()->create([
        'slug' => 'test-collection',
        'default' => true,
        'language_id' => Language::getDefault()->id,
    ]);

    $found = $this->manager->getBySlug('test-collection');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($collection->id);
});

test('it returns null for non-existent slug', function () {
    $found = $this->manager->getBySlug('non-existent-slug');

    expect($found)->toBeNull();
});

test('it can get child collection by parent and child slug', function () {
    $language = Language::getDefault();

    $parent = Collection::factory()->create();

    $parent->urls()->create([
        'slug' => 'parent-collection',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child = Collection::factory()->create([
        'collection_group_id' => $parent->collection_group_id,
    ]);

    $child->urls()->create([
        'slug' => 'child-collection',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child->appendToNode($parent)->save();

    $found = $this->manager->getBySlug('parent-collection', 'child-collection');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($child->id);
});

test('it can get breadcrumbs for collection', function () {
    $language = Language::getDefault();

    $collection = Collection::factory()->create([
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

    $breadcrumbs = $this->manager->getBreadcrumbs($collection);

    expect($breadcrumbs)->toHaveCount(1)
        ->and($breadcrumbs->first())->toBeInstanceOf(Breadcrumb::class)
        ->and($breadcrumbs->first()->slug)->toBe('test-collection');
});

test('it includes ancestors in breadcrumbs', function () {
    $language = Language::getDefault();

    $parent = Collection::factory()->create([
        'attribute_data' => collect([
            'name' => new Text('Parent'),
        ]),
    ]);

    $parent->urls()->create([
        'slug' => 'parent',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $child = Collection::factory()->create([
        'collection_group_id' => $parent->collection_group_id,
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

    $breadcrumbs = $this->manager->getBreadcrumbs($child);

    expect($breadcrumbs)->toHaveCount(2)
        ->and($breadcrumbs->first()->slug)->toBe('parent')
        ->and($breadcrumbs->last()->slug)->toBe('child');
});
