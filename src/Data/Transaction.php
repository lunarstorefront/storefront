<?php

namespace Lunar\Storefront\Data;

use Lunar\DataTypes\Price;
use Spatie\LaravelData\Data;

/** @typescript */
class Transaction extends Data
{
    public function __construct(
        public string $cardType,
        public ?int $lastFour,
        public string $type,
        public bool $success,
        public Price $amount,
        public ?string $notes,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Transaction $transaction): self
    {
        return new self(
            cardType: $transaction->card_type,
            lastFour: $transaction->last_four,
            type: $transaction->type,
            success: $transaction->success,
            amount: $transaction->amount,
            notes: $transaction->notes,
        );
    }
}
