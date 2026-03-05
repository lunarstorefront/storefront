<?php

use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Storefront\Data\Breadcrumb;
use Lunar\Storefront\Managers\CollectionManager;

beforeEach(function () {
    $this->manager = new CollectionManager();

    Language::factory()->create(['default' => true]);
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

test('it returns show page props with collection and breadcrumbs', function () {
    $language = Language::getDefault();

    $searchResults = Mockery::mock(\Lunar\Search\Data\SearchResults::class);

    $this->app->bind(\Lunar\Storefront\Contracts\SearchManager::class, function () use ($searchResults) {
        return new class($searchResults) implements \Lunar\Storefront\Contracts\SearchManager
        {
            public function __construct(private $searchResults) {}

            public function getResults(
                ?string $query = null,
                ?\Lunar\Models\Contracts\Collection $collection = null,
                int $perPage = 40,
                ?string $sort = 'relevance:asc',
                array $filters = [],
            ): \Lunar\Search\Data\SearchResults {
                return $this->searchResults;
            }
        };
    });

    $collection = Collection::factory()->create([
        'attribute_data' => collect([
            'name' => new Text('Test Collection'),
        ]),
    ]);

    $collection->urls()->create([
        'slug' => 'test-show-collection',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $collection->load('defaultUrl', 'ancestors.defaultUrl');

    $result = $this->manager->getShowPageProps($collection, null, 40);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['collection', 'breadcrumbs', 'results'])
        ->and($result['collection'])->toBeInstanceOf(\Lunar\Storefront\Data\Collection::class)
        ->and($result['breadcrumbs'])->toHaveCount(1)
        ->and($result['results'])->toBe($searchResults);
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
