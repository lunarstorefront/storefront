<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\DataTypes\Price;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class CartLine extends Data
{
    public function __construct(
        public string $id,
        public Lazy|string $productId,
        public Lazy|int $stockLevel,
        public Lazy|string $identifier,
        public Lazy|string $name,
        public Lazy|string $slug,
        public Lazy|string|null $thumbnail,
        public int $quantity,
        public Price $unitPrice,
        public Price $unitPriceIncTax,
        public Price $subTotal,
        public Price $discountTotal,
        public Price $subTotalDiscounted,
        public Price $taxAmount,
        public Price $total,
        /** @var ProductOption[] */
        public Collection $optionValues
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\CartLine $cartLine): self
    {
        return new self(
            id: $cartLine->id,
            productId: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product_id),
            stockLevel: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getTotalInventory()),
            identifier: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getIdentifier()),
            name: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product->attr('name')),
            slug: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product->defaultUrl?->slug),
            thumbnail: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getThumbnail()?->getUrl('thumbnail')),
            quantity: $cartLine->quantity,
            unitPrice: $cartLine->unitPrice,
            unitPriceIncTax: $cartLine->unitPriceInclTax,
            subTotal: $cartLine->subTotal,
            discountTotal: $cartLine->discountTotal,
            subTotalDiscounted: $cartLine->subTotalDiscounted,
            taxAmount: $cartLine->taxAmount,
            total: $cartLine->total,
            optionValues: ProductOptionValue::collect(
                $cartLine->purchasable->values
            )
        );
    }
}
