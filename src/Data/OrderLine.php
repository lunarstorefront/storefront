<?php

namespace Lunar\Storefront\Data;

use Lunar\Sales\Models\OrderLine as OrderLineModel;
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
        public int $unitPrice,
        public int $unitQuantity,
        public int $quantity,
        public int $subTotal,
        public int $discountTotal,
        public int $taxTotal,
        public int $total
    ) {}

    public static function fromModel(OrderLineModel $orderLine): self
    {
        return new self(
            id: $orderLine->id,
            type: $orderLine->type,
            thumbnail: $orderLine->purchasable?->thumbnail?->getUrl('thumbnail'),
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
