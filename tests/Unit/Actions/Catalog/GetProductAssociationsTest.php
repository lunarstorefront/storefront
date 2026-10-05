<?php

use Illuminate\Support\Facades\DB;
use Lunar\Core\Enums\ProductAssociation as ProductAssociationType;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductAssociation;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\Region;
use Lunar\Storefront\Actions\Catalog\GetProductAssociations;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $this->currency->id,
        'language_id' => $language->id,
    ]);
});

test('it returns empty collection when product has no associations', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductAssociations;
    $result = $action->get($product);

    expect($result)->toBeEmpty();
});

test('it returns product associations', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()->for($productType)->create();
    $associatedProduct = Product::factory()->for($productType)->create();

    $associatedProduct->urls()->create([
        'slug' => 'associated-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    ProductAssociation::create([
        'product_parent_id' => $product->id,
        'product_target_id' => $associatedProduct->id,
        'type' => 'cross-sell',
    ]);

    $action = new GetProductAssociations;
    $result = $action->get($product);

    expect($result)->toHaveCount(1)
        ->and($result->first()->target->id)->toBe($associatedProduct->id);
});

test('it can filter by association type', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()->for($productType)->create();

    $crossSellProduct = Product::factory()->for($productType)->create();
    $crossSellProduct->urls()->create([
        'slug' => 'cross-sell',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $upSellProduct = Product::factory()->for($productType)->create();
    $upSellProduct->urls()->create([
        'slug' => 'up-sell',
        'default' => true,
        'language_id' => $language->id,
    ]);

    ProductAssociation::create([
        'product_parent_id' => $product->id,
        'product_target_id' => $crossSellProduct->id,
        'type' => 'cross-sell',
    ]);

    ProductAssociation::create([
        'product_parent_id' => $product->id,
        'product_target_id' => $upSellProduct->id,
        'type' => 'up-sell',
    ]);

    $action = new GetProductAssociations;

    // Get all associations
    $all = $action->get($product);
    expect($all)->toHaveCount(2);

    // Filter by cross-sell type
    $crossSells = $action->get($product, ProductAssociationType::CROSS_SELL);
    expect($crossSells)->toHaveCount(1)
        ->and($crossSells->first()->target->id)->toBe($crossSellProduct->id);
});

test('it can get inverse associations', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $parentProduct = Product::factory()->for($productType)->create();
    $parentProduct->urls()->create([
        'slug' => 'parent-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $childProduct = Product::factory()->for($productType)->create();

    ProductAssociation::create([
        'product_parent_id' => $parentProduct->id,
        'product_target_id' => $childProduct->id,
        'type' => 'cross-sell',
    ]);

    $action = new GetProductAssociations;

    // Normal direction: parent -> child (target)
    $normalResult = $action->get($parentProduct, inverse: false);
    expect($normalResult)->toHaveCount(1)
        ->and($normalResult->first()->target->id)->toBe($childProduct->id);

    // Inverse direction: child -> parent
    $inverseResult = $action->get($childProduct, inverse: true);
    expect($inverseResult)->toHaveCount(1)
        ->and($inverseResult->first()->parent->id)->toBe($parentProduct->id);
});

test('it eager loads target product relations', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()->for($productType)->create();
    $associatedProduct = Product::factory()->for($productType)->create();

    $associatedProduct->urls()->create([
        'slug' => 'associated',
        'default' => true,
        'language_id' => $language->id,
    ]);

    ProductAssociation::create([
        'product_parent_id' => $product->id,
        'product_target_id' => $associatedProduct->id,
        'type' => 'cross-sell',
    ]);

    $action = new GetProductAssociations;
    $result = $action->get($product);

    $association = $result->first();

    expect($association->relationLoaded('target'))->toBeTrue()
        ->and($association->target->relationLoaded('defaultUrl'))->toBeTrue();
});

test('it gets the associations of several products in one query, in product order', function () {
    $productType = ProductType::factory()->create();

    [$first, $second, $unrelated] = Product::factory()->for($productType)->count(3)->create();
    [$a, $b, $c, $d] = Product::factory()->for($productType)->count(4)->create();

    ProductAssociation::create(['product_parent_id' => $second->id, 'product_target_id' => $c->id, 'type' => 'cross-sell', 'sort' => 1]);
    ProductAssociation::create(['product_parent_id' => $first->id, 'product_target_id' => $b->id, 'type' => 'cross-sell', 'sort' => 2]);
    ProductAssociation::create(['product_parent_id' => $first->id, 'product_target_id' => $a->id, 'type' => 'cross-sell', 'sort' => 1]);
    ProductAssociation::create(['product_parent_id' => $unrelated->id, 'product_target_id' => $d->id, 'type' => 'cross-sell']);

    DB::enableQueryLog();
    $result = (new GetProductAssociations)->getForProducts([$first, $second]);
    $associationQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'product_associations'))
        ->count();
    DB::disableQueryLog();

    expect($result->map(fn ($association) => $association->target->id)->all())
        ->toBe([$a->id, $b->id, $c->id])
        ->and($associationQueries)->toBe(1)
        ->and($result->first()->target->relationLoaded('defaultUrl'))->toBeTrue();
});

test('it filters several products\' associations by type and direction', function () {
    $productType = ProductType::factory()->create();

    [$parent, $crossSell, $upSell] = Product::factory()->for($productType)->count(3)->create();

    ProductAssociation::create(['product_parent_id' => $parent->id, 'product_target_id' => $crossSell->id, 'type' => 'cross-sell']);
    ProductAssociation::create(['product_parent_id' => $parent->id, 'product_target_id' => $upSell->id, 'type' => 'up-sell']);

    $action = new GetProductAssociations;

    expect($action->getForProducts([$parent], ProductAssociationType::CROSS_SELL)->pluck('product_target_id')->all())
        ->toBe([$crossSell->id])
        ->and($action->getForProducts([$crossSell], inverse: true)->first()->parent->id)
        ->toBe($parent->id);
});

test('it returns no associations for no products', function () {
    expect((new GetProductAssociations)->getForProducts([]))->toBeEmpty();
});
