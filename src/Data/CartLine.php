<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\CartLine as CartLineModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
        public ?string $unitPriceFormatted,
        public int $subTotal,
        public int $discountTotal,
        public int $taxAmount,
        public int $total,
        public ?string $totalFormattedExclTax,
        public ?string $totalFormattedInclTax,
        /** @var ProductOption[] */
        public Lazy|Collection $optionValues
    ) {}

    public static function fromModel(CartLineModel $cartLine): self
    {

        return new self(
            id: $cartLine->id,
            productId: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product_id),
            stockLevel: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getTotalInventory()),
            identifier: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->getIdentifier()),
            name: Lazy::whenLoaded('purchasable', $cartLine, fn () => (string) $cartLine->purchasable->product->translate('name')),
            slug: Lazy::whenLoaded('purchasable', $cartLine, fn () => $cartLine->purchasable->product->defaultUrl?->slug),
            thumbnail: Lazy::whenLoaded('purchasable', $cartLine, fn () => ($cartLine->purchasable->thumbnail ?? $cartLine->purchasable->product->thumbnail)?->getUrl('small')),
            quantity: $cartLine->quantity,
            unitPrice: $cartLine->unitPrice->value,
            unitPriceFormatted: $cartLine->unitPrice->format(),
            subTotal: $cartLine->subTotal->value,
            discountTotal: $cartLine->discountTotal->value,
            taxAmount: $cartLine->taxAmount->value,
            total: $cartLine->total->value,
            totalFormattedExclTax: $cartLine->subTotal->format(),
            totalFormattedInclTax: $cartLine->total->format(),
            optionValues: Lazy::whenLoaded(
                'purchasable',
                $cartLine,
                fn () => ProductOptionValue::collect($cartLine->purchasable->values)
            )
        );
    }
}
