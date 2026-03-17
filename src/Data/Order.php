<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Sales\Models\Order as OrderModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class Order extends Data
{
    public function __construct(
        public string $id,
        public string $status,
        public string $reference,
        public int $subTotal,
        public int $discountTotal,
        public int $shippingTotal,
        public int $taxTotal,
        public int $total,
        public ?string $notes,
        public string $currencyCode,
        public ?\DateTime $placedAt,
        public Lazy|OrderAddress $billingAddress,
        public Lazy|OrderAddress $shippingAddress,
        public Lazy|Collection $transactions,
        public Lazy|Collection|null $physicalLines = null,
    ) {}

    public static function fromModel(OrderModel $order): self
    {
        return new self(
            id: $order->id,
            status: $order->status,
            reference: $order->reference,
            subTotal: $order->sub_total,
            discountTotal: $order->discount_total,
            shippingTotal: $order->shipping_total,
            taxTotal: $order->tax_total,
            total: $order->total,
            notes: $order->notes,
            currencyCode: $order->currency_code,
            placedAt: $order->placed_at,
            billingAddress: Lazy::whenLoaded('billingAddress', $order, fn () => OrderAddress::from($order->billingAddress)),
            shippingAddress: Lazy::whenLoaded('shippingAddress', $order, fn () => OrderAddress::from($order->shippingAddress)),
            transactions: Lazy::whenLoaded('transactions', $order, fn () => Transaction::collect($order->transactions)),
            physicalLines: Lazy::whenLoaded('physicalLines', $order, fn () => OrderLine::collect($order->physicalLines))
        );
    }
}
