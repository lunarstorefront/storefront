<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Base\Enums\ProductAssociation;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Actions\Catalog\GetProductAssociations;
use Lunar\Storefront\Actions\Catalog\GetProductBySlug;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;
use Lunar\Storefront\Facades\Storefront;

class ProductManager implements \Lunar\Storefront\Contracts\ProductManager
{
    public function getModelBySlug(string $slug): Product
    {
        return (new GetProductBySlug)->get($slug, asModel: true);
    }

    public function getPermutations(Product $product, ?Collection $options = null): Collection
    {
        return (new GetProductOptionPermutations)->get(
            $options ?: $this->getOptions($product),
            $product
        );
    }

    public function getOptions(Product $product): Collection
    {
        return (new GetProductOptions)->get($product);
    }

    public function getAssociations(Product $product, ProductAssociation $association): Collection
    {
        $associations = (new GetProductAssociations)->get($product, type: $association);

        return \Lunar\Storefront\Data\Product::collect(
            $associations->pluck('target')
        );
    }

    public function getShowPageProps(Product $product, ?string $optionHash, int $quantity = 1): array
    {
        $productOptions = $this->getOptions($product);
        $selectedOptions = Storefront::variants()->decryptOptions($optionHash);
        $currentVariant = Storefront::variants()->getProvidedVariant($product, $optionHash);

        return [
            'product' => fn () => \Lunar\Storefront\Data\Product::from($product),
            'crossSell' => fn () => $this->getAssociations($product, ProductAssociation::CROSS_SELL),
            'productOptions' => fn () => \Lunar\Storefront\Data\ProductOption::collect($productOptions),
            'breadcrumbs' => fn () => ($firstCollection = $product->collections->first())
                ? Storefront::collections()->getBreadcrumbs($firstCollection)
                : collect(),
            'collection' => function () use ($product) {
                if ($collection = $product->collections->first()) {
                    return \Lunar\Storefront\Data\Collection::from($collection);
                }

                return null;
            },
            'selectedOptions' => fn () => collect($selectedOptions)->mapWithKeys(
                fn ($value, $option) => [$option => $value]
            ),
            'currentVariant' => fn () => $currentVariant ? \Lunar\Storefront\Data\ProductVariant::from($currentVariant) : null,
            'initialQuantity' => $quantity,
            'permutations' => fn () => $this->getPermutations($product, $productOptions),
        ];
    }
}
