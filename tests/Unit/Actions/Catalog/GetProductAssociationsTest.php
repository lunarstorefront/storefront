<?php

use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductAssociation;
use Lunar\Catalog\Models\ProductType;
use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
use Lunar\Storefront\Actions\Catalog\GetProductAssociations;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns empty collection when product has no associations', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductAssociations();
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

    $action = new GetProductAssociations();
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

    $action = new GetProductAssociations();

    // Get all associations
    $all = $action->get($product);
    expect($all)->toHaveCount(2);

    // Filter by cross-sell type
    $crossSells = $action->get($product, \Lunar\Catalog\Enums\ProductAssociationType::CrossSell);
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

    $action = new GetProductAssociations();

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

    $action = new GetProductAssociations();
    $result = $action->get($product);

    $association = $result->first();

    expect($association->relationLoaded('target'))->toBeTrue()
        ->and($association->target->relationLoaded('defaultUrl'))->toBeTrue();
});
