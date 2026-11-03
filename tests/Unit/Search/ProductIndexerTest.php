<?php

use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Search\ProductIndexer;

beforeEach(function () {
    Language::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    $this->currency = Currency::factory()->create(['default' => true, 'code' => 'GBP']);
    $this->defaultGroup = CustomerGroup::factory()->create(['default' => true]);
    $this->taxClass = TaxClass::factory()->create(['default' => true]);
    $this->product = Product::factory()->for(ProductType::factory())->create();
});

function priceVariant(int $amount, ?int $groupId = null): void
{
    $variant = ProductVariant::factory()
        ->for(test()->product)
        ->for(test()->taxClass)
        ->create();

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => test()->currency->id,
        'customer_group_id' => $groupId,
        'min_quantity' => 1,
        'price' => $amount,
    ]);
}

function indexed(Product $product): array
{
    $indexer = new ProductIndexer;
    $loaded = $indexer->makeAllSearchableUsing(Product::query())->findOrFail($product->id);

    return $indexer->toSearchableArray($loaded);
}

function makeCollection(string $name, string $status = 'published', ?Collection $parent = null): Collection
{
    $group = CollectionGroup::first() ?? CollectionGroup::factory()->create();
    $collection = Collection::factory()->create([
        'status' => $status,
        'collection_group_id' => $group->id,
        'name' => collect(['en' => $name]),
    ]);

    if ($parent) {
        $collection->appendToNode($parent)->save();
    }

    return $collection;
}

it('indexes the cheapest price across variants', function () {
    priceVariant(1000);
    priceVariant(800);

    $data = indexed($this->product);

    expect($data['min_price'])->toBe(800)
        ->and($data['min_prices'])->toBe(['GBP' => 800])
        ->and($data['prices']['default']['GBP']['exclTax']['value'])->toBe(800)
        ->and($data['prices']['default']['GBP'])->toHaveKeys(['inclTax', 'exclTax']);
});

it('excludes prices for non default customer groups', function () {
    priceVariant(1000);
    priceVariant(500, CustomerGroup::factory()->create(['default' => false, 'handle' => 'trade'])->id);

    $data = indexed($this->product);

    expect($data['min_price'])->toBe(1000)
        ->and($data['prices'])->toHaveKey('default')
        ->and($data['prices'])->not->toHaveKey('trade');
});

it('gives a null min price when the product has no prices', function () {
    $data = indexed($this->product);

    expect($data['min_price'])->toBeNull()
        ->and($data['min_prices'])->toBe([])
        ->and($data['prices'])->toBe([]);
});

it('indexes the collection hierarchy of published collections', function () {
    $parent = makeCollection('Parent');
    $child = makeCollection('Child', parent: $parent);
    $this->product->collections()->attach($child->id);

    $tree = indexed($this->product)['collectionTree'];

    expect($tree['lvl0'])->toBe(['Parent'])
        ->and($tree['lvl1'])->toBe(['Parent > Child'])
        ->and($tree['lvl2'])->toBe([]);
});

it('leaves out draft collections', function () {
    $draft = makeCollection('Hidden', 'draft');
    $this->product->collections()->attach($draft->id);

    expect(indexed($this->product)['collectionTree'])->toBe(['lvl0' => [], 'lvl1' => [], 'lvl2' => []]);
});

it('declares the new sortable and filterable fields', function () {
    $indexer = new ProductIndexer;

    expect($indexer->getSortableFields())->toContain('min_price')
        ->and($indexer->getFilterableFields())->toContain(
            'min_price', 'collectionTree.lvl0', 'collectionTree.lvl1', 'collectionTree.lvl2',
        );
});

it('skips a published collection under an unpublished parent', function () {
    $parent = makeCollection('Hidden parent', 'draft');
    $child = makeCollection('Visible child', parent: $parent);
    $this->product->collections()->attach($child->id);

    expect(indexed($this->product)['collectionTree'])->toBe(['lvl0' => [], 'lvl1' => [], 'lvl2' => []]);
});

it('indexes several products from one query without lazy loading', function () {
    $parent = makeCollection('Parent');
    $child = makeCollection('Child', parent: $parent);
    $second = Product::factory()->for(ProductType::first())->create();

    foreach ([$this->product, $second] as $product) {
        $product->collections()->attach($child->id);
        $variant = ProductVariant::factory()->for($product)->for($this->taxClass)->create();
        Price::factory()->create([
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'customer_group_id' => null,
            'min_quantity' => 1,
            'price' => 700,
        ]);
    }

    $indexer = new ProductIndexer;
    $rows = $indexer->makeAllSearchableUsing(Product::query())->get()
        ->map(fn (Product $product) => $indexer->toSearchableArray($product));

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('min_price')->all())->toBe([700, 700])
        ->and($rows->first()['collectionTree']['lvl1'])->toBe(['Parent > Child']);
});
