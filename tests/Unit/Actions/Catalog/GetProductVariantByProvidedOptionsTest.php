<?php

use Lunar\FieldTypes\Text;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductOption;
use Lunar\Catalog\Models\ProductOptionValue;
use Lunar\Catalog\Models\ProductType;
use Lunar\Catalog\Models\ProductVariant;
use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
use Lunar\Kernel\Models\TaxClass;
use Lunar\Storefront\Actions\Catalog\GetProductVariantByProvidedOptions;
use Lunar\Storefront\Managers\VariantManager;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it returns null when hash is null', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductVariantByProvidedOptions();
    $result = $action->get($product, null);

    expect($result)->toBeNull();
});

test('it returns null when hash is empty string', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductVariantByProvidedOptions();
    // Empty string is truthy for the null check but decrypts to empty array
    $result = $action->get($product, '');

    expect($result)->toBeNull();
});

test('it finds variant by encrypted options hash', function () {
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
        ->create(['sku' => 'RED-VARIANT']);

    $variant->values()->attach($red);

    // Create hash using VariantManager (same as GetProductOptionPermutations)
    $variantManager = new VariantManager();
    $hash = $variantManager->encryptOptions([
        $colorOption->id => $red->id,
    ]);

    $action = new GetProductVariantByProvidedOptions();
    $result = $action->get($product, $hash);

    expect($result)->not->toBeNull()
        ->and($result->sku)->toBe('RED-VARIANT');
});

test('it returns null for invalid hash', function () {
    $productType = ProductType::factory()->create();
    $product = Product::factory()->for($productType)->create();

    $action = new GetProductVariantByProvidedOptions();
    $result = $action->get($product, 'invalid-hash-value');

    // Invalid hash decrypts to empty array, which should return first variant or null
    expect($result)->toBeNull();
});

test('it finds correct variant with multiple options', function () {
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

    // Create specific variant: Blue + Large
    $targetVariant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['sku' => 'BLUE-LARGE']);
    $targetVariant->values()->attach([$blue->id, $large->id]);

    // Create other variants
    $otherVariant = ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['sku' => 'RED-SMALL']);
    $otherVariant->values()->attach([$red->id, $small->id]);

    $variantManager = new VariantManager();
    $hash = $variantManager->encryptOptions([
        $colorOption->id => $blue->id,
        $sizeOption->id => $large->id,
    ]);

    $action = new GetProductVariantByProvidedOptions();
    $result = $action->get($product, $hash);

    expect($result)->not->toBeNull()
        ->and($result->sku)->toBe('BLUE-LARGE');
});

test('it eager loads prices and tax class relations', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $currency = Currency::getDefault();
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

    \Lunar\Catalog\Models\Price::factory()->create([
        'priceable_type' => ProductVariant::class,
        'priceable_id' => $variant->id,
        'currency_id' => $currency->id,
        'price' => 1000,
    ]);

    $variantManager = new VariantManager();
    $hash = $variantManager->encryptOptions([
        $colorOption->id => $red->id,
    ]);

    $action = new GetProductVariantByProvidedOptions();
    $result = $action->get($product, $hash);

    expect($result->relationLoaded('prices'))->toBeTrue()
        ->and($result->relationLoaded('taxClass'))->toBeTrue();

    // If prices exist, verify currency is eager loaded
    if ($result->prices->isNotEmpty()) {
        expect($result->prices->first()->relationLoaded('currency'))->toBeTrue();
    }
});

test('it returns null when variant does not belong to product', function () {
    $productType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $product1 = Product::factory()->for($productType)->create();
    $product2 = Product::factory()->for($productType)->create();

    $colorOption = ProductOption::factory()->create([
        'name' => collect(['en' => new Text('Color')]),
    ]);

    $red = ProductOptionValue::factory()
        ->for($colorOption, 'option')
        ->create(['name' => collect(['en' => new Text('Red')])]);

    // Create variant for product2, not product1
    $variant = ProductVariant::factory()
        ->for($product2)
        ->for($taxClass)
        ->create();
    $variant->values()->attach($red);

    $variantManager = new VariantManager();
    $hash = $variantManager->encryptOptions([
        $colorOption->id => $red->id,
    ]);

    $action = new GetProductVariantByProvidedOptions();
    // Query product1, but variant belongs to product2
    $result = $action->get($product1, $hash);

    expect($result)->toBeNull();
});
