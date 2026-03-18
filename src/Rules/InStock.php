<?php

namespace Lunar\Storefront\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Lunar\Catalog\Models\ProductVariant;
use Lunar\Sales\Models\Cart;
use Lunar\Sales\Models\CartLine;

class InStock implements ValidationRule, DataAwareRule
{
    public function __construct(
        protected ?Cart $cart = null,
        protected ?int $cartLineId = null,
        /**
         * All the data under validation.
         *
         * @var array<string, mixed>
         */
        protected array $data = []
    ) {}

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        /**
         * Ensure we cast things like "1e+23" to their integer value.
         */
        $value = (int) $value;

        if ($value > 1000000) {
            $fail('Please enter a quantity less than 1,000,000');

            return;
        }

        $variant = null;

        if ($this->cartLineId) {
            $cartLine = CartLine::find($this->cartLineId);

            if (! $cartLine) {
                $fail('Cart line not found.');
                return;
            }

            $variant = $cartLine->purchasable;
        }

        if ($sku = $this->data['sku'] ?? null) {
            $variant = ProductVariant::whereSku($sku)->first();
        }

        if (! $variant) {
            $fail('Invalid SKU');

            return;
        }

        $existingLine = $this->cart?->lines->first(
            fn ($line) => $line->purchasable_id == $variant->id
        );


        $value = $value + ($existingLine?->quantity ?: 0);

        if (! $variant->canBeFulfilledAtQuantity($value)) {
            $fail(
                (
                $existingLine ?
                    'Insufficient stock for total quantity '.$value :
                    'Insufficient stock.'
                )
            );
        }

        if ($variant->min_quantity > $value) {
            $fail('You must enter a minimum quantity of '.$variant->min_quantity);
        }

        if (($value % ($variant->quantity_increment ?? 1)) !== 0) {
            $fail('Quantity must be in increments of '.$variant->quantity_increment);
        }
    }

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
