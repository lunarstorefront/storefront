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
use Lunar\Storefront\Actions\Catalog\GetProductOptions;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns empty collection for product with no options', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductOptions();
    $options = $action->get($product);

    expect($options)->toBeEmpty();
});

test('it returns product options', function () {
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

    $action = new GetProductOptions();
    $options = $action->get($product);

    expect($options)->toHaveCount(1)
        ->and($options->first()->id)->toBe($colorOption->id);
});

test('it only returns option values that have variants for this product', function () {
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

    $green = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Green')])]);

    // Only create variants for red and blue, not green
    $variantRed = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();
    $variantRed->values()->attach($red);

    $variantBlue = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();
    $variantBlue->values()->attach($blue);

    $product->productOptions()->attach($colorOption, ['position' => 1]);

    $action = new GetProductOptions();
    $options = $action->get($product);

    // The option should only have red and blue values, not green
    $valueIds = $options->first()->values->pluck('id')->all();

    expect($valueIds)->toContain($red->id)
        ->toContain($blue->id)
        ->not->toContain($green->id);
});

test('it returns multiple options', function () {
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

    $small = ProductOptionValue::factory()
        ->for($sizeOption, 'option')
        ->create(['name' => collect(['en' => new Text('Small')])]);

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();
    $variant->values()->attach([$red->id, $small->id]);

    $product->productOptions()->attach([
        $colorOption->id => ['position' => 1],
        $sizeOption->id => ['position' => 2],
    ]);

    $action = new GetProductOptions();
    $options = $action->get($product);

    expect($options)->toHaveCount(2);
});

test('it eager loads values with their option relation', function () {
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

    $action = new GetProductOptions();
    $options = $action->get($product);

    $option = $options->first();
    $value = $option->values->first();

    expect($option->relationLoaded('values'))->toBeTrue()
        ->and($value->relationLoaded('option'))->toBeTrue();
});
