<?php

namespace Lunar\Storefront\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Lunar\Core\Contracts\CouponValidator;

class ValidCoupon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! app(CouponValidator::class)->validate((string) $value)) {
            $fail('Invalid coupon code.');
        }
    }
}
