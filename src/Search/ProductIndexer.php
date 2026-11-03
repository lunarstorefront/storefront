<?php

namespace Lunar\Storefront\Search;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Search\ProductIndexer as BaseProductIndexer;
use Lunar\Core\States\Collection\Published;

/**
 * Adds what a storefront listing needs on top of Lunar's product indexer:
 * prices (`min_price`, `min_prices`, `prices`) and the collection hierarchy
 * (`collectionTree`), the ids of the collections a product is in
 * (`collection_ids`, which the collection pages filter on) and a plain `name`.
 * Only public prices are indexed, because search documents
 * are readable by every visitor.
 *
 * Register it under `indexers` in `config/lunar/search.php`.
 */
class ProductIndexer extends BaseProductIndexer
{
    public function getSortableFields(): array
    {
        return array_merge(parent::getSortableFields(), ['min_price']);
    }

    public function getFilterableFields(): array
    {
        return array_merge(parent::getFilterableFields(), [
            'min_price',
            'collection_ids',
            'collectionTree.lvl0',
            'collectionTree.lvl1',
            'collectionTree.lvl2',
        ]);
    }

    public function makeAllSearchableUsing(Builder $query): Builder
    {
        return parent::makeAllSearchableUsing($query)->with([
            'prices.currency',
            'prices.customerGroup',
            'prices.priceable.taxClass',
            'collections.ancestors',
        ]);
    }

    public function toSearchableArray(Model $model): array
    {
        return array_merge(
            parent::toSearchableArray($model),
            $this->mapPricing($model),
            [
                'collection_ids' => $model->collections->pluck('id')->map(fn ($id): string => (string) $id)->values()->all(),
                'collectionTree' => $this->mapCollectionTree($model),
            ],
            $this->mapPlainName($model),
        );
    }

    /**
     * Core indexes the name per locale (`name_en`). Listings and the default
     * `query_by` need one plain `name` in the default language.
     *
     * @return array{name?: string}
     */
    protected function mapPlainName(Model $model): array
    {
        if (! $model instanceof Product) {
            return [];
        }

        // The `name` attribute (attribute data), as the panel and storefront
        // show it, falling back to the locale keyed `name` column.
        $name = $model->translateAttribute('name') ?? $model->getAttribute('name');
        $name = $name instanceof Arrayable ? $name->toArray() : $name;

        if (is_array($name)) {
            $name = Arr::get($name, app()->getLocale(), Arr::first($name));
        }

        $name = (string) $name;

        return $name !== '' ? ['name' => $name] : [];
    }

    /**
     * `prices` is the `SearchPriceMap` the JS price display reads:
     * group handle ("default" for ungrouped and default-group prices) to
     * currency code to `{ inclTax, exclTax }`, each `{ value, formatted }` in
     * minor units. Tax comes from Price::priceIncTax() and priceExTax(), which
     * use the default tax zone and the variant's tax class.
     *
     * @return array{min_price: int|null, min_prices: array<string, int>, prices: array<string, array<string, mixed>>}
     */
    protected function mapPricing(Model $model): array
    {
        $cheapest = $this->publicPrices($model->prices)
            ->groupBy(fn (Price $price): string => $price->currency->code)
            ->map(fn ($prices): Price => $prices->sortBy('price')->first());

        $default = $cheapest->first(fn (Price $price): bool => (bool) $price->currency->default);

        return [
            'min_price' => $default !== null ? (int) $default->price : null,
            'min_prices' => $cheapest->map(fn (Price $price): int => (int) $price->price)->all(),
            'prices' => $cheapest
                ->map(fn (Price $price): array => $this->mapPrice($price))
                ->mapToGroups(fn (array $leaf, string $code): array => ['default' => [$code => $leaf]])
                ->map(fn ($leaves) => $leaves->collapse()->all())
                ->all(),
        ];
    }

    /**
     * @return array{inclTax: array{value: int, formatted: string|null}, exclTax: array{value: int, formatted: string|null}}
     */
    protected function mapPrice(Price $price): array
    {
        $inc = $price->priceIncTax();
        $exc = $price->priceExTax();

        return [
            'inclTax' => ['value' => (int) $inc->value, 'formatted' => $inc->format()],
            'exclTax' => ['value' => (int) $exc->value, 'formatted' => $exc->format()],
        ];
    }

    /**
     * Prices anyone may see for quantity 1: ungrouped, or the default customer
     * group's. Other groups' prices must not reach the shared index.
     *
     * @param  EloquentCollection<int, Price>  $prices
     * @return EloquentCollection<int, Price>
     */
    protected function publicPrices(EloquentCollection $prices): EloquentCollection
    {
        return $prices->filter(
            fn (Price $price): bool => (int) $price->min_quantity === 1
                && ($price->customer_group_id === null || (bool) $price->customerGroup?->default),
        );
    }

    /**
     * Hierarchical breadcrumbs per published collection: for Root > A > B,
     * lvl0 is "Root", lvl1 "Root > A", lvl2 "Root > A > B".
     *
     * @return array{lvl0: list<string>, lvl1: list<string>, lvl2: list<string>}
     */
    protected function mapCollectionTree(Model $model): array
    {
        $tree = ['lvl0' => [], 'lvl1' => [], 'lvl2' => []];

        foreach ($model->collections as $collection) {
            $chain = $collection->ancestors->sortBy('_lft')->push($collection);

            // A hidden ancestor must not leak its name through a visible child.
            if ($chain->contains(fn (Collection $node): bool => (string) $node->status !== Published::$name)) {
                continue;
            }

            $names = $chain
                ->map(fn (Collection $node): string => (string) $node->translate('name'))
                ->values();

            foreach ($names->take(3) as $level => $unused) {
                $tree['lvl'.$level][] = $names->take($level + 1)->implode(' > ');
            }
        }

        return array_map(fn (array $values): array => array_values(array_unique($values)), $tree);
    }
}
