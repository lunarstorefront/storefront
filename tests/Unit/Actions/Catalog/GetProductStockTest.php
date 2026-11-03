<?php

use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Actions\Catalog\GetProductStock;

function productWithVariant(int $stock = 0, int $backorder = 0, string $policy = 'in_stock'): Product
{
    $product = Product::factory()->for(ProductType::factory())->create();

    ProductVariant::factory()->for($product)->create([
        'stock_available' => $stock,
        'backorder' => $backorder,
        'selling_policy' => match ($policy) {
            'backorder' => SellingPolicy::InStockOrOnBackorder,
            'always' => SellingPolicy::Always,
            default => SellingPolicy::InStock,
        },
    ]);

    return $product->load('variants');
}

it('derives the stock state', function (int $stock, int $backorder, string $policy, string $state) {
    $product = productWithVariant(stock: $stock, backorder: $backorder, policy: $policy);

    expect((new GetProductStock)->handle($product)->state)->toBe($state);
})->with([
    'plenty' => [20, 0, 'in_stock', 'in_stock'],
    'few left' => [3, 0, 'in_stock', 'low_stock'],
    'backorder allowed' => [0, 10, 'backorder', 'backorder'],
    'none' => [0, 0, 'in_stock', 'out_of_stock'],
]);

it('hides the count unless configured', function () {
    $product = productWithVariant(stock: 3);

    expect((new GetProductStock)->handle($product)->count)->toBeNull();

    config(['storefront.stock.show_quantity' => true]);

    expect((new GetProductStock)->handle($product)->count)->toBe(3);
});

it('reports out of stock for a product with no variants', function () {
    $product = Product::factory()->for(ProductType::factory())->create()->load('variants');

    $stock = (new GetProductStock)->handle($product);

    expect($stock->state)->toBe('out_of_stock')
        ->and($stock->maxQuantity)->toBeNull();
});

it('reads the low stock threshold from config', function () {
    config(['storefront.stock.low_threshold' => 2]);

    expect((new GetProductStock)->handle(productWithVariant(stock: 3))->state)->toBe('in_stock');
});

it('limits the quantity to the inventory unless it is unlimited or beyond the practical limit', function () {
    $action = new GetProductStock;

    expect($action->handle(productWithVariant(stock: 7))->maxQuantity)->toBe(7)
        ->and($action->handle(productWithVariant(stock: 7, policy: 'always'))->maxQuantity)->toBeNull()
        ->and($action->handle(productWithVariant(stock: 1500))->maxQuantity)->toBeNull();

    config(['storefront.stock.practical_limit' => 5]);

    expect($action->handle(productWithVariant(stock: 7))->maxQuantity)->toBeNull();
});

it('uses the given variant instead of the lowest id one', function () {
    $product = productWithVariant(stock: 0);
    $other = ProductVariant::factory()->for($product)->create([
        'stock_available' => 20,
        'selling_policy' => SellingPolicy::InStock,
    ]);

    $action = new GetProductStock;

    expect($action->handle($product)->state)->toBe('out_of_stock')
        ->and($action->handle($product, $other)->state)->toBe('in_stock');
});
