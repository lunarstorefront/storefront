<?php

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Storefront\Rules\InStock;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();
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
        ->create([
            'sku' => 'STOCK-TEST',
            'stock' => 100,
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
        ->create([
            'sku' => 'MIN-QTY-TEST',
            'stock' => 100,
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
        ->create([
            'sku' => 'INCREMENT-TEST',
            'stock' => 100,
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
