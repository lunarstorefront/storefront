<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\Customer as CustomerModel;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Customer extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $companyName,
        public ?string $taxIdentifier,
    ) {}

    public static function fromModel(CustomerModel $customer): self
    {
        return new self(
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            companyName: $customer->company_name,
            taxIdentifier: $customer->tax_identifier,
        );
    }
}
