<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Reporting;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\PaymentMethod;
use BudgetBook\Domain\Reporting\BalanceSheetService;
use BudgetBook\Domain\Reporting\CashFlowStatementService;
use BudgetBook\Domain\Reporting\DailyReport;
use BudgetBook\Domain\Reporting\DailyReportService;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryJournalEntryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DailyReportService::class)]
#[CoversClass(DailyReport::class)]
final class DailyReportServiceTest extends TestCase
{
    private const USER = 1;

    public function test_daily_report_includes_snapshot_and_entries_for_the_day(): void
    {
        $accounts = new InMemoryAccountRepository();
        $entries = new InMemoryJournalEntryRepository();

        $cashId = $this->saveAccount($accounts, '현금', AccountType::ASSET, 'CASH', CashFlowSection::NONE, '100000');
        $foodId = $this->saveAccount($accounts, '식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING, '0');

        $entryToday = $this->recordEntry($entries, '2026-04-22', $foodId, $cashId, '15000');
        $this->recordEntry($entries, '2026-04-21', $foodId, $cashId, '3000');  // different day

        $service = new DailyReportService(
            new BalanceSheetService($accounts, $entries),
            new CashFlowStatementService($accounts, $entries),
            $entries,
        );

        $report = $service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($report->balanceSheet->isBalanced());
        self::assertTrue($report->balanceSheet->totalAssets()->isEqualTo(BigDecimal::of('82000.00')));
        self::assertCount(1, $report->dayEntries);
        self::assertSame((int) $entryToday->id(), $report->dayEntries[0]->id());
        self::assertTrue($report->cashFlowForDay->operatingOutflow()->isEqualTo(BigDecimal::of('15000.00')));
    }

    private function saveAccount(
        InMemoryAccountRepository $accounts,
        string $name,
        AccountType $type,
        ?string $subtype,
        CashFlowSection $section,
        string $openingBalance,
    ): int {
        $account = Account::create(
            userId: self::USER,
            name: $name,
            type: $type,
            subtype: $subtype,
            section: $section,
            openingBalance: BigDecimal::of($openingBalance),
        );
        $accounts->save($account);
        return (int) $account->id();
    }

    private function recordEntry(
        InMemoryJournalEntryRepository $entries,
        string $date,
        int $debitId,
        int $creditId,
        string $amount,
    ): JournalEntry {
        $entry = JournalEntry::record(
            userId: self::USER,
            occurredOn: new DateTimeImmutable($date),
            memo: null,
            merchant: null,
            paymentMethod: PaymentMethod::CASH,
            lines: [
                JournalEntryLine::debit($debitId, BigDecimal::of($amount)),
                JournalEntryLine::credit($creditId, BigDecimal::of($amount)),
            ],
        );
        $entries->save($entry);
        return $entry;
    }
}
