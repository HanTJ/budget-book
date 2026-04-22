<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\AccountType;

final class BalanceSheetLine
{
    public function __construct(
        public readonly int $accountId,
        public readonly string $name,
        public readonly AccountType $type,
        public readonly ?string $subtype,
        public readonly BigDecimal $balance,
    ) {
    }
}
