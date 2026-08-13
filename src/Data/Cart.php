<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Cart as CartModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Cart extends Data
{
    public function __construct(
        public string $fingerprint,
        public ?string $coupon,
        public int $subTotal,
        public ?string $subTotalFormatted,
        public int $subTotalDiscounted,
        public int $discountTotal,
        public int $shippingTotal,
        public int $taxTotal,
        public ?string $taxTotalFormatted,
        public int $total,
        public ?string $totalFormatted,
        public Currency $currency,
        public ?int $linesCount,
        public ?int $totalQuantity,
        /** @var Lazy|CartLine[] */
        public Lazy|Collection $lines,
        public Lazy|CartAddress|null $shippingAddress,
        public Lazy|CartAddress|null $billingAddress,
        public ?ShippingBreakdown $shippingBreakdown,
    ) {}

    public static function fromModel(CartModel $cart): self
    {
        return new self(
            fingerprint: $cart->fingerprint() ?? $cart->generateFingerprint(),
            coupon: $cart->coupon_code,
            subTotal: $cart->subTotal->value,
            subTotalFormatted: $cart->subTotal->format(),
            subTotalDiscounted: $cart->subTotalDiscounted->value,
            discountTotal: $cart->discountTotal->value,
            shippingTotal: $cart->shippingSubTotal->value,
            taxTotal: $cart->taxTotal->value,
            taxTotalFormatted: $cart->taxTotal->format(),
            total: $cart->total->value,
            totalFormatted: $cart->total->format(),
            currency: Currency::from($cart->currency),
            linesCount: $cart->lines_count,
            totalQuantity: $cart->lines_sum_quantity !== null
                ? (int) $cart->lines_sum_quantity
                : ($cart->relationLoaded('lines') ? $cart->lines->sum('quantity') : null),
            lines: Lazy::whenLoaded('lines', $cart, fn () => CartLine::collect($cart->lines)),
            shippingAddress: Lazy::whenLoaded('shippingAddress', $cart, fn () => CartAddress::from($cart->shippingAddress)),
            billingAddress: Lazy::whenLoaded('billingAddress', $cart, fn () => CartAddress::from($cart->billingAddress)),
            shippingBreakdown: $cart->shipping_breakdown
                ? ShippingBreakdown::fromValueObject($cart->shipping_breakdown)
                : null,
        );
    }
}
