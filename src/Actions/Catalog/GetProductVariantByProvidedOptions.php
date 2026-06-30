<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Managers\VariantManager;

class GetProductVariantByProvidedOptions
{
    public function get(Product $product, ?string $hash): ?ProductVariant
    {
        if (! $hash) {
            return null;
        }

        $options = (new VariantManager)->decryptOptions($hash);

        return $product->variants()
            ->with([
                'prices.currency',
                'prices.priceable',
                'taxClass',
            ])
            ->when(
                count($options),
                function (Builder $query) use ($options) {
                    collect($options)->filter()->each(
                        fn ($selectedValue) => $query->whereHas(
                            'values',
                            fn (Builder $relation) => $relation->where('value_id', $selectedValue)
                        )
                    );
                }
            )->first();
    }
}
