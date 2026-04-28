<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Kernel\DataObjects\PriceValue;
use Lunar\Sales\DataObjects\ShippingBreakdown;
use Lunar\Sales\Models\Cart as CartModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
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
        public ?string $totalFormattedExclTax,
        public ?string $totalFormattedInclTax,
        public Currency $currency,
        public ?int $linesCount,
        /** @var CartLine[] */
        public Lazy|Collection $lines,
        /** @var CartAddress */
        public Lazy|CartAddress|null $shippingAddress,
        /** @var CartAddress */
        public Lazy|CartAddress|null $billingAddress,
        public ?ShippingBreakdown $shippingBreakdown,
    ) {}

    public static function fromModel(CartModel $cart): self
    {
        $totalExclTax = (int) $cart->total - (int) $cart->tax_total;
        $totalExclTaxFormatted = (new PriceValue($totalExclTax, $cart->currency))->format();

        return new self(
            fingerprint: $cart->fingerprint ?? $cart->generateFingerprint(),
            coupon: $cart->coupon_code,
            subTotal: $cart->sub_total,
            subTotalFormatted: $cart->format('sub_total'),
            subTotalDiscounted: $cart->sub_total_discounted,
            discountTotal: $cart->discount_total,
            shippingTotal: $cart->shipping_sub_total,
            taxTotal: $cart->tax_total,
            taxTotalFormatted: $cart->format('tax_total'),
            total: $cart->total,
            totalFormattedExclTax: $totalExclTaxFormatted,
            totalFormattedInclTax: $cart->format('total'),
            currency: Currency::from($cart->currency),
            linesCount: $cart->lines_count,
            lines: Lazy::whenLoaded('lines', $cart, fn () => CartLine::collect($cart->lines)),
            shippingAddress: Lazy::whenLoaded('shippingAddress', $cart, fn () => CartAddress::from($cart->shippingAddress)),
            billingAddress: Lazy::whenLoaded('billingAddress', $cart, fn () => CartAddress::from($cart->billingAddress)),
            shippingBreakdown: $cart->shipping_breakdown,
        );
    }
}
