<?php

namespace Lunar\Storefront\Actions\Cart;

use Illuminate\Database\Eloquent\Collection;
use Lunar\Core\Exceptions\Carts\CartException;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Data\ReorderLine;
use Lunar\Storefront\Data\ReorderResult;

class AddOrderToCart
{
    /**
     * Add a past order's product lines to the shopper's cart at today's price
     * and stock. A line that can no longer be supplied is skipped and reported
     * rather than dropped silently; the rest are still added, alongside what
     * the cart already holds. Ownership of the order is the caller's to check.
     */
    public function add(Order $order): ReorderResult
    {
        /** @var Collection<int, OrderLine> $lines */
        $lines = $order->productLines()
            ->with('purchasable.product')
            ->get();

        $result = new ReorderResult;
        $inCart = CartSession::current(calculate: false)?->lines()->get(['purchasable_type', 'purchasable_id', 'quantity']);

        foreach ($lines as $line) {
            $variant = $line->purchasable instanceof ProductVariant ? $line->purchasable : null;
            $alreadyInCart = $variant === null ? 0 : (int) $inCart?->where('purchasable_id', $variant->id)
                ->where('purchasable_type', $variant->getMorphClass())
                ->sum('quantity');

            $reason = $this->skipReason($variant, $line->quantity, $alreadyInCart);

            if ($reason === null && $variant !== null) {
                try {
                    // Proxied to the session's cart, which it creates on first add.
                    CartSession::add($variant, $line->quantity, refresh: false); // @phpstan-ignore staticMethod.notFound
                } catch (CartException) {
                    $reason = ReorderLine::UNAVAILABLE;
                }
            }

            if ($reason === null) {
                $result->added[] = $this->line($line);
            } else {
                $result->skipped[] = $this->line($line, $reason);
            }
        }

        if ($result->added !== []) {
            CartSession::current(calculate: false)?->refresh()->recalculate();
        }

        return $result;
    }

    /**
     * Why a past line cannot go back in the cart, mirroring the add-to-cart
     * rules: the variant must still be on sale, the stock must cover it on top
     * of what the cart holds, and the quantity must meet today's rules.
     */
    protected function skipReason(?ProductVariant $variant, int $quantity, int $alreadyInCart): ?string
    {
        if ($variant === null || ! $variant->isPurchasable()) {
            return ReorderLine::UNAVAILABLE;
        }

        $total = $quantity + $alreadyInCart;

        if (! $variant->canBeFulfilledAtQuantity($total)) {
            return ReorderLine::OUT_OF_STOCK;
        }

        if ($variant->min_quantity > $total || $total % ($variant->quantity_increment ?: 1) !== 0) {
            return ReorderLine::QUANTITY;
        }

        return null;
    }

    protected function line(OrderLine $line, ?string $reason = null): ReorderLine
    {
        return new ReorderLine(
            identifier: (string) $line->identifier,
            name: (string) $line->description,
            quantity: (int) $line->quantity,
            reason: $reason,
        );
    }
}
