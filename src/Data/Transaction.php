<?php

namespace Lunar\Storefront\Data;

use Lunar\Sales\Models\Transaction as TransactionModel;
use Spatie\LaravelData\Data;

/** @typescript */
class Transaction extends Data
{
    public function __construct(
        public string $cardType,
        public ?int $lastFour,
        public string $type,
        public bool $success,
        public int $amount,
        public ?string $notes,
    ) {}

    public static function fromModel(TransactionModel $transaction): self
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
