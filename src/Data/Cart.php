<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\DataTypes\Price;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class Cart extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $coupon,
        public Price $subTotal,
        public Price $subTotalDiscounted,
        public Price                 $discountTotal,
        public Price                 $shippingTotal,
        public Price                 $taxTotal,
        public Price                 $total,
        public Currency          $currency,
        public ?int $linesCount,
        /** @var CartLine[] */
        public Lazy|Collection       $lines,
        /** @var Discount[] */
        public Lazy|Collection       $discounts,
        /** @var CartAddress */
        public Lazy|CartAddress|null $shippingAddress,
        /** @var CartAddress */
        public Lazy|CartAddress|null $billingAddress,
        public ShippingBreakdown     $shippingBreakdown,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Cart $cart): self
    {
        return new self(
            id: $cart->id,
            coupon: $cart->coupon_code,
            subTotal: $cart->subTotal,
            subTotalDiscounted: $cart->subTotalDiscounted,
            discountTotal: $cart->discountTotal,
            shippingTotal: $cart->shippingSubTotal,
            taxTotal: $cart->taxTotal,
            total: $cart->total,
            currency: Currency::from($cart->currency),
            linesCount: $cart->linesCount,
            lines: Lazy::whenLoaded('lines', $cart, fn () => CartLine::collect($cart->lines)),
            discounts: Lazy::when(fn () => $cart->discounts, fn () => Discount::collect($cart->discounts->pluck('discount'))),
            shippingAddress: Lazy::whenLoaded('shippingAddress', $cart, fn () => CartAddress::from($cart->shippingAddress)),
            billingAddress: Lazy::whenLoaded('billingAddress', $cart, fn () => CartAddress::from($cart->billingAddress)),
            shippingBreakdown: $cart->shippingBreakdown,
        );
    }
}
