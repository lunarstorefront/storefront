<?php

use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Rules\InStock;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);
});

test('it fails when quantity exceeds 1,000,000', function () {
    $rule = new InStock;
    $rule->setData(['sku' => 'TEST']);

    $failures = [];
    $rule->validate('quantity', 1000001, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('Please enter a quantity less than 1,000,000');
});

test('it fails when sku does not exist', function () {
    $rule = new InStock;
    $rule->setData([]);

    $failures = [];
    $rule->validate('quantity', 1, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('Invalid SKU');
});

test('it fails when cart line id does not exist', function () {
    $rule = new InStock(cartLineId: 999);
    $rule->setData([]);

    $failures = [];
    $rule->validate('quantity', 1, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('Cart line not found.');
});

test('it passes for valid sku with sufficient stock', function () {
    $product = Product::factory()
        ->for($this->productType)
        ->create();

    ProductVariant::factory()
        ->for($product)
        ->for($this->taxClass)
        ->inStock(100)
        ->create([
            'sku' => 'STOCK-TEST',
        ]);

    $rule = new InStock;
    $rule->setData(['sku' => 'STOCK-TEST']);

    $failures = [];
    $rule->validate('quantity', 5, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toBeEmpty();
});

test('it fails when quantity below min_quantity', function () {
    $product = Product::factory()
        ->for($this->productType)
        ->create();

    ProductVariant::factory()
        ->for($product)
        ->for($this->taxClass)
        ->inStock(100)
        ->create([
            'sku' => 'MIN-QTY-TEST',
            'min_quantity' => 10,
        ]);

    $rule = new InStock;
    $rule->setData(['sku' => 'MIN-QTY-TEST']);

    $failures = [];
    $rule->validate('quantity', 5, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('You must enter a minimum quantity of 10');
});

test('it fails when quantity not in increment', function () {
    $product = Product::factory()
        ->for($this->productType)
        ->create();

    ProductVariant::factory()
        ->for($product)
        ->for($this->taxClass)
        ->inStock(100)
        ->create([
            'sku' => 'INCREMENT-TEST',
            'quantity_increment' => 5,
        ]);

    $rule = new InStock;
    $rule->setData(['sku' => 'INCREMENT-TEST']);

    $failures = [];
    $rule->validate('quantity', 7, function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('Quantity must be in increments of 5');
});

test('it casts scientific notation quantity to integer', function () {
    $rule = new InStock;
    $rule->setData(['sku' => 'TEST']);

    $failures = [];
    $rule->validate('quantity', '1e+23', function ($message) use (&$failures) {
        $failures[] = $message;
    });

    expect($failures)->toContain('Please enter a quantity less than 1,000,000');
});
