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
        /**
         * The manufacturer part number at the time the order was placed (see
         * mpnOf()). Null when the variant had none, or the line is not a
         * product variant.
         */
        public ?string $mpn,
        public int $unitPrice,
        public ?string $unitPriceFormatted,
        public int $unitQuantity,
        public int $quantity,
        public int $subTotal,
        public ?string $subTotalFormatted,
        public int $discountTotal,
        public ?string $discountTotalFormatted,
        public int $taxTotal,
        public ?string $taxTotalFormatted,
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
            mpn: static::mpnOf($orderLine),
            unitPrice: $orderLine->unit_price,
            unitPriceFormatted: $format($orderLine->unit_price),
            unitQuantity: $orderLine->unit_quantity,
            quantity: $orderLine->quantity,
            subTotal: $orderLine->sub_total,
            subTotalFormatted: $format($orderLine->sub_total),
            discountTotal: $orderLine->discount_total,
            discountTotalFormatted: $format($orderLine->discount_total),
            taxTotal: $orderLine->tax_total,
            taxTotalFormatted: $format($orderLine->tax_total),
            total: $orderLine->total,
            totalFormatted: $format($orderLine->total),
        );
    }

    /**
     * The part number StampOrderLinePartNumbers recorded on the line's meta
     * when the order was placed, even if that was null. Lines without one
     * (orders placed before it was recorded) read the variant's current one.
     */
    protected static function mpnOf(OrderLineModel $orderLine): ?string
    {
        $meta = (array) $orderLine->meta;

        if (array_key_exists('mpn', $meta)) {
            return is_string($meta['mpn']) && $meta['mpn'] !== '' ? $meta['mpn'] : null;
        }

        $purchasable = $orderLine->purchasable;

        return $purchasable instanceof ProductVariant ? $purchasable->mpn : null;
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
