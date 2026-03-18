<?php

namespace Lunar\Storefront\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Lunar\Promotions\Actions\ValidateCoupon;
use Lunar\Sales\Facades\CartSession;

class ValidCoupon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $result = (new ValidateCoupon)->execute(
            code: $value,
            cart: CartSession::current(),
        );

        if (! $result->valid) {
            $fail($result->reason ?? 'Invalid coupon code.');
        }
    }
}
