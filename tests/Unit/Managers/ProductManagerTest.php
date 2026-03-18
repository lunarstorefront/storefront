<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductOption;
use Lunar\Catalog\Models\ProductOptionValue;
use Lunar\Catalog\Models\ProductType;
use Lunar\Catalog\Models\ProductVariant;
use Lunar\Kernel\FieldTypes\Text;
use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
use Lunar\Kernel\Models\Region;
use Lunar\Kernel\Models\TaxClass;
use Lunar\Storefront\Data\ProductOptionPermutation;
use Lunar\Storefront\Managers\ProductManager;

beforeEach(function () {
    $this->manager = new ProductManager;

    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);
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

test('it can get product model by slug', function () {
    $productType = ProductType::factory()->create();
    $language = Language::getDefault();

    $product = Product::factory()
        ->for($productType)
        ->create(['status' => 'active']);

    $product->urls()->create([
        'slug' => 'test-product',
        'default' => true,
        'language_id' => $language->id,
    ]);

    $result = $this->manager->getModelBySlug('test-product');

    expect($result->id)->toBe($product->id);
});

test('it throws when product slug not found', function () {
    $this->manager->getModelBySlug('nonexistent');
})->throws(ModelNotFoundException::class);
