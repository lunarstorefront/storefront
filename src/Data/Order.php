<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\Models\Order as OrderModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Order extends Data
{
    public function __construct(
        public string $id,
        /** The fulfilment status, kept under its pre-2.x name for existing consumers. */
        public string $status,
        public string $paymentStatus,
        public string $fulfilmentStatus,
        public ?string $reference,
        public ?string $customerReference,
        public int $subTotal,
        public ?string $subTotalFormatted,
        public int $discountTotal,
        public ?string $discountTotalFormatted,
        public int $shippingTotal,
        public ?string $shippingTotalFormatted,
        public int $taxTotal,
        public ?string $taxTotalFormatted,
        public int $total,
        public ?string $totalFormatted,
        public ?string $notes,
        public string $currencyCode,
        /** Serialised as an ISO 8601 string. */
        #[LiteralTypeScriptType('string | null')]
        public ?\DateTimeInterface $placedAt,
        public Lazy|OrderAddress|null $billingAddress,
        public Lazy|OrderAddress|null $shippingAddress,
        /** @var Lazy|Transaction[] */
        public Lazy|Collection $transactions,
        /** @var Lazy|OrderLine[]|null */
        public Lazy|Collection|null $physicalLines = null,
        /** @var Lazy|OrderLine[]|null */
        public Lazy|Collection|null $shippingLines = null,
    ) {}

    public static function fromModel(OrderModel $order): self
    {
        $fulfilmentStatus = (string) $order->fulfilment_status?->getValue();
        $currency = $order->resolveCurrency();
        $format = fn (int $value): ?string => (new PriceValue($value, $currency))->format();

        return new self(
            id: (string) $order->id,
            status: $fulfilmentStatus,
            paymentStatus: (string) $order->payment_status?->getValue(),
            fulfilmentStatus: $fulfilmentStatus,
            reference: $order->reference,
            customerReference: $order->customer_reference,
            subTotal: $order->sub_total,
            subTotalFormatted: $format($order->sub_total),
            discountTotal: $order->discount_total,
            discountTotalFormatted: $format($order->discount_total),
            shippingTotal: $order->shipping_total,
            shippingTotalFormatted: $format($order->shipping_total),
            taxTotal: $order->tax_total,
            taxTotalFormatted: $format($order->tax_total),
            total: $order->total,
            totalFormatted: $format($order->total),
            notes: $order->notes,
            currencyCode: $order->currency_code,
            placedAt: $order->placed_at,
            billingAddress: Lazy::whenLoaded('billingAddress', $order, fn () => $order->billingAddress ? OrderAddress::fromModel($order->billingAddress) : null),
            shippingAddress: Lazy::whenLoaded('shippingAddress', $order, fn () => $order->shippingAddress ? OrderAddress::fromModel($order->shippingAddress) : null),
            transactions: Lazy::whenLoaded('transactions', $order, fn () => Transaction::collect($order->transactions)),
            physicalLines: Lazy::whenLoaded('physicalLines', $order, fn () => OrderLine::collect($order->physicalLines->each->setRelation('order', $order))),
            shippingLines: Lazy::whenLoaded('shippingLines', $order, fn () => OrderLine::collect($order->shippingLines->each->setRelation('order', $order))),
        );
    }
}
