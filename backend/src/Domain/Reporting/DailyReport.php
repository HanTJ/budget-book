<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use BudgetBook\Domain\Ledger\JournalEntry;
use DateTimeImmutable;

final class DailyReport
{
    /**
     * @param list<JournalEntry> $dayEntries
     */
    public function __construct(
        public readonly DateTimeImmutable $date,
        public readonly BalanceSheet $balanceSheet,
        public readonly CashFlowStatement $cashFlowForDay,
        public readonly array $dayEntries,
    ) {
    }
}
