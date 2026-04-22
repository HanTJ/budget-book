<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use BudgetBook\Domain\Ledger\JournalEntryRepository;
use DateTimeImmutable;

final class DailyReportService
{
    public function __construct(
        private readonly BalanceSheetService $balanceSheet,
        private readonly CashFlowStatementService $cashFlow,
        private readonly JournalEntryRepository $entries,
    ) {
    }

    public function compute(int $userId, DateTimeImmutable $date): DailyReport
    {
        $sheet = $this->balanceSheet->compute($userId, $date);
        $flow = $this->cashFlow->compute($userId, $date, $date);
        $dayEntries = $this->entries->listForUser($userId, $date, $date);

        return new DailyReport(
            date: $date,
            balanceSheet: $sheet,
            cashFlowForDay: $flow,
            dayEntries: $dayEntries,
        );
    }
}
