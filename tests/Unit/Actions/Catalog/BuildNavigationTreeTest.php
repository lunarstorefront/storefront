<?php

use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\Language;
use Lunar\Storefront\Actions\Catalog\BuildNavigationTree;
use Lunar\Storefront\Data\NavigationItem;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
});

function navCollection(CollectionGroup $group, string $name, ?Collection $parent = null, bool $withUrl = true): Collection
{
    $collection = Collection::factory()->create([
        'status' => 'published',
        'collection_group_id' => $group->id,
        'name' => collect([app()->getLocale() => $name]),
    ]);

    if ($withUrl) {
        $collection->urls()->create([
            'slug' => str($name)->slug()->toString(),
            'default' => true,
            'language_id' => Language::getDefault()->id,
        ]);
    }

    if ($parent) {
        $collection->appendToNode($parent)->save();
    }

    return $collection;
}

function seedNavigation(): void
{
    $main = CollectionGroup::factory()->create(['handle' => 'main']);
    $other = CollectionGroup::factory()->create(['handle' => 'other']);

    $tools = navCollection($main, 'Tools');
    $drills = navCollection($main, 'Drills', $tools);
    navCollection($main, 'Cordless', $drills);
    navCollection($main, 'Garden');
    navCollection($other, 'Hidden');
}

it('builds a tree of the requested depth in nested-set order', function () {
    seedNavigation();

    $tree = (new BuildNavigationTree)->handle('main', 2);

    expect(collect($tree)->pluck('name')->all())->toBe(['Tools', 'Garden'])
        ->and(collect($tree[0]->children)->pluck('name')->all())->toBe(['Drills'])
        ->and($tree[0]->children[0]->children)->toBe([]);
});

it('only includes the requested group', function () {
    seedNavigation();

    expect(collect((new BuildNavigationTree)->handle('main'))->pluck('name'))->not->toContain('Hidden');
});

it('returns an empty list for an unknown group', function () {
    seedNavigation();

    expect((new BuildNavigationTree)->handle('nope'))->toBe([]);
});

it('maps the default url slug and null when there is none', function () {
    $main = CollectionGroup::factory()->create(['handle' => 'main']);
    navCollection($main, 'Tools');
    navCollection($main, 'No Url', withUrl: false);

    $tree = (new BuildNavigationTree)->handle('main', 1);

    expect($tree[0])->toBeInstanceOf(NavigationItem::class)
        ->and($tree[0]->slug)->toBe('tools')
        ->and($tree[1]->slug)->toBeNull();
});

it('goes deeper when asked', function () {
    seedNavigation();

    $tree = (new BuildNavigationTree)->handle('main', 3);

    expect($tree[0]->children[0]->children[0]->name)->toBe('Cordless');
});

it('reads defaults from config', function () {
    seedNavigation();

    config(['storefront.navigation' => ['group' => 'main', 'depth' => 1]]);

    $tree = (new BuildNavigationTree)->handle();

    expect(collect($tree)->pluck('name')->all())->toBe(['Tools', 'Garden'])
        ->and($tree[0]->children)->toBe([]);
});

it('uses a fixed number of queries per level', function () {
    seedNavigation();

    DB::enableQueryLog();
    (new BuildNavigationTree)->handle('main', 3);

    // group lookup + (collections + urls) per level
    expect(DB::getQueryLog())->toHaveCount(7);
});
