<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class Customer extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $companyName,
        public ?string $taxIdentifier,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Customer $customer): self
    {
        return new self(
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            companyName: $customer->company_name,
            taxIdentifier: $customer->tax_identifier,
        );
    }
}
