<?php

use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Data\Product as ProductData;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->product = Product::factory()->for(ProductType::factory())->create();
    $this->product->urls()->create([
        'slug' => 'alpha2',
        'default' => true,
        'language_id' => $language->id,
    ]);
});

/** Give the product a variant with the given stock settings. */
function addVariant(Product $product, TaxClass $taxClass, array $attributes): ProductVariant
{
    return ProductVariant::factory()->for($product)->for($taxClass)->create($attributes);
}

/** The product as a card payload, with the stock fields requested. */
function cardPayload(Product $product): array
{
    return ProductData::from($product->load('variants', 'defaultUrl'))
        ->include('availability', 'variantCount')
        ->exclude('description', 'attributeData')
        ->toArray();
}

test('it leaves the stock fields out unless requested', function () {
    addVariant($this->product, $this->taxClass, ['stock_available' => 5]);

    $data = ProductData::from($this->product->load('variants', 'defaultUrl'))->toArray();

    expect($data)->not->toHaveKey('availability')
        ->and($data)->not->toHaveKey('variantCount');
});

test('it reports a default variant with stock as in stock', function () {
    addVariant($this->product, $this->taxClass, [
        'stock_available' => 5,
        'selling_policy' => SellingPolicy::InStock,
    ]);

    expect(cardPayload($this->product)['availability'])->toBe('in_stock');
});

test('it reports a variant that can still be bought without stock as backorder', function (SellingPolicy $policy, int $backorder) {
    addVariant($this->product, $this->taxClass, [
        'stock_available' => 0,
        'backorder' => $backorder,
        'selling_policy' => $policy,
    ]);

    expect(cardPayload($this->product)['availability'])->toBe('backorder');
})->with([
    'always sellable' => [SellingPolicy::Always, 0],
    'backorder allowance' => [SellingPolicy::InStockOrOnBackorder, 3],
]);

test('it reports a variant that cannot be bought as out of stock', function (SellingPolicy $policy, int $backorder) {
    addVariant($this->product, $this->taxClass, [
        'stock_available' => 0,
        'backorder' => $backorder,
        'selling_policy' => $policy,
    ]);

    expect(cardPayload($this->product)['availability'])->toBe('out_of_stock');
})->with([
    // A backorder figure is ignored when the policy only sells stock on hand.
    'in stock only' => [SellingPolicy::InStock, 3],
    'no backorder allowance left' => [SellingPolicy::InStockOrOnBackorder, 0],
]);

test('it reports a product without variants as out of stock', function () {
    expect(cardPayload($this->product)['availability'])->toBe('out_of_stock')
        ->and(cardPayload($this->product)['variantCount'])->toBe(0);
});

test('it counts the variants so a card knows when options must be chosen', function () {
    addVariant($this->product, $this->taxClass, ['stock_available' => 5]);
    addVariant($this->product, $this->taxClass, ['stock_available' => 5]);

    expect(cardPayload($this->product)['variantCount'])->toBe(2);
});
