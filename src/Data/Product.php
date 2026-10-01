<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Core\Models\Product as ProductModel;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Lunar\Storefront\Facades\Storefront;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Product extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        /** Default-included lazy: present unless a consumer excludes it for slim payloads (e.g. card rows). */
        public Lazy|string|null $description,
        /**
         * Default-included lazy — see $description.
         *
         * @var Collection<AttributeDataValue>
         */
        public Lazy|Collection $attributeData,
        public Lazy|Media $thumbnail,
        /** @var Media[] */
        public Lazy|Collection $images,
        /** @var MediaCollection[] */
        public Lazy|Collection $media,
        public ?Url $url = null,
        /** Opt-in lazy: request via ->include('brand'). */
        public Lazy|string|null $brand = null,
        /** Opt-in lazy: default variant SKU, request via ->include('sku'). */
        public Lazy|string|null $sku = null,
        /**
         * Opt-in lazy: single-unit price for the current currency + customer
         * groups, resolved through the pricing manager at include time.
         * Request via ->include('price').
         */
        public Lazy|Price|null $price = null,
        /**
         * Opt-in lazy: whether the default variant can be bought now, on the
         * same rule the cart applies (`canBeFulfilledAtQuantity`), so a card
         * never offers an add the cart will reject. Request via
         * ->include('availability'). One of in_stock, backorder, out_of_stock.
         */
        public Lazy|string|null $availability = null,
        /**
         * Opt-in lazy: number of variants, so a card can send the shopper to
         * choose options rather than add the default one. Request via
         * ->include('variantCount').
         */
        public Lazy|int|null $variantCount = null,
    ) {}

    public static function fromModel(ProductModel $product): self
    {
        return new self(
            name: (string) $product->translate('name'),
            description: Lazy::create(fn (): ?string => $product->translate('description'))->defaultIncluded(),
            attributeData: Lazy::create(fn (): Collection => static::mapAttributes($product))->defaultIncluded(),
            thumbnail: Lazy::whenLoaded('thumbnail', $product, fn () => Media::from($product->thumbnail)),
            images: Lazy::whenLoaded('media', $product, fn () => Media::collect($product->images)),
            media: Lazy::whenLoaded('media', $product, fn () => $product->media->groupBy('collection_name')->map(
                fn ($media, $collection) => new MediaCollection(
                    name: Str::title($collection),
                    handle: $collection,
                    files: Media::collect($media)
                )
            )->values()),
            url: Url::from($product->defaultUrl),
            brand: Lazy::create(fn (): ?string => $product->brand?->name),
            sku: Lazy::create(fn (): ?string => $product->variants->first()?->sku),
            price: Lazy::create(function () use ($product): ?Price {
                $variant = $product->variants->first();
                $pricing = $variant ? Storefront::pricing()->getPricing($variant) : null;

                return $pricing ? Storefront::pricing()->getQuantifiedPrice($pricing, 1) : null;
            }),
            availability: Lazy::create(fn (): string => static::availabilityOf($product)),
            variantCount: Lazy::create(fn (): int => $product->variants->count()),
        );
    }

    /**
     * Stock state of the default variant for display: in stock when there is
     * stock on hand, backorder when the cart would still accept one without
     * it, and out of stock otherwise (including no variant at all).
     *
     * @return 'in_stock'|'backorder'|'out_of_stock'
     */
    protected static function availabilityOf(ProductModel $product): string
    {
        $variant = $product->variants->first();

        if (! $variant instanceof ProductVariant || ! $variant->canBeFulfilledAtQuantity(1)) {
            return 'out_of_stock';
        }

        return $variant->stock_available > 0 ? 'in_stock' : 'backorder';
    }
}
