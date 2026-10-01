<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\Models\OrderLine as OrderLineModel;
use Lunar\Core\Models\ProductVariant;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
        public ?string $unitPriceFormatted,
        public int $unitQuantity,
        public int $quantity,
        public int $subTotal,
        public ?string $subTotalFormatted,
        public int $discountTotal,
        public int $taxTotal,
        public int $total,
        public ?string $totalFormatted,
    ) {}

    public static function fromModel(OrderLineModel $orderLine): self
    {
        $currency = $orderLine->resolveCurrency();
        $format = fn (int $value): ?string => (new PriceValue($value, $currency))->format();

        return new self(
            id: (string) $orderLine->id,
            type: $orderLine->type,
            thumbnail: static::thumbnailOf($orderLine),
            description: $orderLine->description,
            option: $orderLine->option,
            identifier: $orderLine->identifier,
            unitPrice: $orderLine->unit_price,
            unitPriceFormatted: $format($orderLine->unit_price),
            unitQuantity: $orderLine->unit_quantity,
            quantity: $orderLine->quantity,
            subTotal: $orderLine->sub_total,
            subTotalFormatted: $format($orderLine->sub_total),
            discountTotal: $orderLine->discount_total,
            taxTotal: $orderLine->tax_total,
            total: $orderLine->total,
            totalFormatted: $format($orderLine->total),
        );
    }

    /**
     * The line's image, as the cart line shows it: the variant's own primary
     * image, else its product's. Variants expose that through
     * getThumbnail(), not a `thumbnail` relation, and the media library has
     * no `thumbnail` conversion, so `small` matches the cart.
     */
    protected static function thumbnailOf(OrderLineModel $orderLine): ?string
    {
        $purchasable = $orderLine->purchasable;

        $media = match (true) {
            $purchasable instanceof ProductVariant => $purchasable->getThumbnail(),
            $purchasable !== null && method_exists($purchasable, 'thumbnail') => $purchasable->thumbnail,
            default => null,
        };

        return $media instanceof Media ? $media->getUrl('small') : null;
    }
}
