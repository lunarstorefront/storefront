<?php

use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\Models\Discount;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Data\Discount as DiscountData;
use Lunar\Storefront\Data\ProductOption as ProductOptionData;
use Lunar\Storefront\Data\ProductOptionValue as ProductOptionValueData;
use Lunar\Storefront\Data\ProductVariant as ProductVariantData;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
});

test('the variant DTO identifies a variant by its public id, as do its values', function () {
    $option = ProductOption::factory()->create(['name' => collect(['en' => new Text('Colour')])]);
    $red = ProductOptionValue::factory()->for($option, 'option')->create(['name' => collect(['en' => new Text('Red')])]);
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(TaxClass::factory())
        ->inStock(5)
        ->create(['selling_policy' => SellingPolicy::InStock]);
    $variant->values()->attach($red);

    $data = ProductVariantData::fromModel($variant->fresh()->load('values'))->toArray();

    expect($data['id'])->toBe($variant->public_id)
        ->and($data['stock'])->toBe(5)
        ->and($data['values'][0]['id'])->toBe($red->public_id);
});

test('the option DTO identifies an option and its values by their public ids', function () {
    $option = ProductOption::factory()->create(['name' => collect(['en' => new Text('Colour')])]);
    $red = ProductOptionValue::factory()->for($option, 'option')->create(['name' => collect(['en' => new Text('Red')])]);

    $data = ProductOptionData::fromModel($option->load('values'))->toArray();

    expect($data['id'])->toBe($option->public_id)
        ->and($data['values'][0]['id'])->toBe($red->public_id)
        ->and(ProductOptionValueData::fromModel($red)->id)->toBe($red->public_id);
});

test('the discount DTO identifies a discount by its public id', function () {
    $discount = Discount::factory()->create(['coupon' => 'SAVE10']);

    expect(DiscountData::fromModel($discount)->id)->toBe($discount->public_id);
});
