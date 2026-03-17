<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Sales\Models\CartLine as CartLineModel;
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
        public int $unitPrice,
        public int $subTotal,
        public int $discountTotal,
        public int $taxAmount,
        public int $total,
        /** @var ProductOption[] */
        public Collection $optionValues
    ) {}

    public static function fromModel(CartLineModel $cartLine): self
    {
        return new self(
            id: $cartLine->id,
            productId: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product_id),
            stockLevel: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getTotalInventory()),
            identifier: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getIdentifier()),
            name: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product->attr('name')),
            slug: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product->defaultUrl?->slug),
            thumbnail: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->thumbnail?->getUrl('thumbnail')),
            quantity: $cartLine->quantity,
            unitPrice: $cartLine->unit_price,
            subTotal: $cartLine->sub_total,
            discountTotal: $cartLine->discount_total,
            taxAmount: $cartLine->tax_amount,
            total: $cartLine->total,
            optionValues: ProductOptionValue::collect(
                $cartLine->purchasable->values
            )
        );
    }
}
