<?php

namespace Lunar\Storefront\Data;

use Lunar\DataTypes\Price;
use Spatie\LaravelData\Data;

/** @typescript */
class OrderLine extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $thumbnail,
        public ?string $description,
        public ?string $option,
        public string $identifier,
        public Price $unitPrice,
        public int $unitQuantity,
        public int $quantity,
        public Price $subTotal,
        public Price $discountTotal,
        public Price $taxTotal,
        public Price $total
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\OrderLine $orderLine): self
    {
        return new self(
            id: $orderLine->id,
            type: $orderLine->type,
            thumbnail: $orderLine->purchasable?->getThumbnail()?->getUrl('thumbnail'),
            description: $orderLine->description,
            option: $orderLine->option,
            identifier: $orderLine->identifier,
            unitPrice: $orderLine->unit_price,
            unitQuantity: $orderLine->unit_quantity,
            quantity: $orderLine->quantity,
            subTotal: $orderLine->sub_total,
            discountTotal: $orderLine->discount_total,
            taxTotal: $orderLine->tax_total,
            total: $orderLine->total,
        );
    }
}
