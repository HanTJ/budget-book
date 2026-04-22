<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use BudgetBook\Domain\Ledger\PaymentMethod;

final class RecordJournalEntryInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $occurredOn,
        public readonly string $amount,
        public readonly PaymentMethod $paymentMethod,
        public readonly int $categoryAccountId,
        public readonly ?int $counterAccountId,
        public readonly ?string $merchant,
        public readonly ?string $memo,
    ) {
    }
}
