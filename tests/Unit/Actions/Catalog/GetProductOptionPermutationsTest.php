<?php

use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;
use Lunar\Storefront\Data\ProductOptionPermutation;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns empty collection when no options', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductOptionPermutations();
    $permutations = $action->get(collect(), $product);

    expect($permutations)->toBeEmpty();
});

test('it generates permutations for single option', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);

    $blue = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Blue')])]);

    $variantRed = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 10]);
    $variantRed->values()->attach($red);

    $variantBlue = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 5]);
    $variantBlue->values()->attach($blue);

    $product->productOptions()->attach($colorOption, ['position' => 1]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    // 2 colors = 2 permutations
    expect($permutations)->toHaveCount(2)
        ->and($permutations->first())->toBeInstanceOf(ProductOptionPermutation::class);
});

test('it generates cartesian product for multiple options', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);
    $sizeOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Size')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);
    $blue = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Blue')])]);

    $small = ProductOptionValue::factory()
        ->for($sizeOption, 'option')
        ->create(['name' => collect(['en' => new Text('Small')])]);
    $large = ProductOptionValue::factory()
        ->for($sizeOption, 'option')
        ->create(['name' => collect(['en' => new Text('Large')])]);

    // Create all 4 variant combinations
    foreach ([$red, $blue] as $color) {
        foreach ([$small, $large] as $size) {
            $variant = ProductVariant::factory()
                ->for($product)
                ->for($taxClass)
                ->create(['stock' => 10]);
            $variant->values()->attach([$color->id, $size->id]);
        }
    }

    $product->productOptions()->attach([
        $colorOption->id => ['position' => 1],
        $sizeOption->id => ['position' => 2],
    ]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    // 2 colors x 2 sizes = 4 permutations
    expect($permutations)->toHaveCount(4);
});

test('it indicates which permutations have variants', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);
    $sizeOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Size')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);
    $blue = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Blue')])]);

    $small = ProductOptionValue::factory()
        ->for($sizeOption, 'option')
        ->create(['name' => collect(['en' => new Text('Small')])]);

    // Only create Red+Small variant (not Blue+Small)
    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 10]);
    $variant->values()->attach([$red->id, $small->id]);

    // Also attach blue to a variant so it shows up in options
    $variantBlue = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 0]);
    $variantBlue->values()->attach([$blue->id, $small->id]);

    $product->productOptions()->attach([
        $colorOption->id => ['position' => 1],
        $sizeOption->id => ['position' => 2],
    ]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    // Both permutations should exist
    expect($permutations)->toHaveCount(2);

    // Find each permutation
    $redSmall = $permutations->first(fn ($p) => in_array($red->id, array_values($p->values)));
    $blueSmall = $permutations->first(fn ($p) => in_array($blue->id, array_values($p->values)));

    expect($redSmall->hasVariant)->toBeTrue()
        ->and($redSmall->stock)->toBe(10)
        ->and($blueSmall->hasVariant)->toBeTrue()
        ->and($blueSmall->stock)->toBe(0);
});

test('permutation includes hash for variant lookup', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();
    $variant->values()->attach($red);

    $product->productOptions()->attach($colorOption, ['position' => 1]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    $permutation = $permutations->first();

    expect($permutation->hash)
        ->toBeString()
        ->not->toBeEmpty();
});

test('permutation includes value names for display', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();
    $variant->values()->attach($red);

    $product->productOptions()->attach($colorOption, ['position' => 1]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    $permutation = $permutations->first();

    expect($permutation->valueNames)
        ->toBeArray()
        ->not->toBeEmpty();
});

test('it detects backorder availability', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);

    $blue = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Blue')])]);

    // Red variant: normal purchasable
    $variantRed = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 0, 'purchasable' => 'in_stock']);
    $variantRed->values()->attach($red);

    // Blue variant: always purchasable (backorder)
    $variantBlue = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 0, 'purchasable' => 'always']);
    $variantBlue->values()->attach($blue);

    $product->productOptions()->attach($colorOption, ['position' => 1]);

    $options = (new GetProductOptions())->get($product);
    $action = new GetProductOptionPermutations();
    $permutations = $action->get($options, $product);

    $redPerm = $permutations->first(fn ($p) => in_array($red->id, array_values($p->values)));
    $bluePerm = $permutations->first(fn ($p) => in_array($blue->id, array_values($p->values)));

    expect($redPerm->backorder)->toBeFalse()
        ->and($bluePerm->backorder)->toBeTrue();
});
