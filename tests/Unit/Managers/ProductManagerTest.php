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
use Lunar\Storefront\Data\ProductOptionPermutation;
use Lunar\Storefront\Managers\ProductManager;

beforeEach(function () {
    $this->manager = new ProductManager();

    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it can get product options', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create();

    $option = ProductOption::factory()->create([
        'name' => collect([
            'en' => new Text('Color'),
        ]),
    ]);

    $optionValue = ProductOptionValue::factory()
        ->for($option, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Red'),
            ]),
        ]);

    $variant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create();

    $variant->values()->attach($optionValue);
    $product->productOptions()->attach($option, ['position' => 1]);

    $options = $this->manager->getOptions($product);

    expect($options)->toHaveCount(1)
        ->and($options->first()->id)->toBe($option->id);
});

test('it returns empty collection when product has no options', function () {
    $productType = ProductType::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create();

    $options = $this->manager->getOptions($product);

    expect($options)->toBeEmpty();
});

test('it can get permutations for product with single option', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create();

    $option = ProductOption::factory()->create([
        'name' => collect([
            'en' => new Text('Size'),
        ]),
    ]);

    $small = ProductOptionValue::factory()
        ->for($option, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Small'),
            ]),
        ]);

    $large = ProductOptionValue::factory()
        ->for($option, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Large'),
            ]),
        ]);

    $variantSmall = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 10]);

    $variantLarge = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 5]);

    $variantSmall->values()->attach($small);
    $variantLarge->values()->attach($large);
    $product->productOptions()->attach($option, ['position' => 1]);

    $permutations = $this->manager->getPermutations($product);

    expect($permutations)->toHaveCount(2)
        ->and($permutations->first())->toBeInstanceOf(ProductOptionPermutation::class);
});

test('it can get permutations for product with multiple options', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $product = Product::factory()
        ->for($productType)
        ->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect([
            'en' => new Text('Color'),
        ]),
    ]);

    $sizeOption = ProductOption::factory()->create([
        'name' => collect([
            'en' => new Text('Size'),
        ]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Red'),
            ]),
        ]);

    $blue = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Blue'),
            ]),
        ]);

    $small = ProductOptionValue::factory()
        ->for($sizeOption, 'option')
        ->create([
            'name' => collect([
                'en' => new Text('Small'),
            ]),
        ]);

    // Create variants for each combination
    $variantRedSmall = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 10]);

    $variantBlueSmall = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['stock' => 5]);

    $variantRedSmall->values()->attach([$red->id, $small->id]);
    $variantBlueSmall->values()->attach([$blue->id, $small->id]);

    $product->productOptions()->attach([
        $colorOption->id => ['position' => 1],
        $sizeOption->id => ['position' => 2],
    ]);

    $permutations = $this->manager->getPermutations($product);

    // 2 colors x 1 size = 2 permutations
    expect($permutations)->toHaveCount(2);
});
