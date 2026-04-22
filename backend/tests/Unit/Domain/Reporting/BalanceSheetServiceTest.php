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
use BudgetBook\Domain\Reporting\BalanceSheet;
use BudgetBook\Domain\Reporting\BalanceSheetService;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryJournalEntryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BalanceSheetService::class)]
#[CoversClass(BalanceSheet::class)]
final class BalanceSheetServiceTest extends TestCase
{
    private const USER = 1;

    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $entries;
    private BalanceSheetService $service;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new InMemoryAccountRepository();
        $this->entries = new InMemoryJournalEntryRepository();
        $this->service = new BalanceSheetService($this->accounts, $this->entries);

        $this->ids = [
            'cash' => $this->saveAccount('현금', AccountType::ASSET, 'CASH', CashFlowSection::NONE, '100000'),
            'bank' => $this->saveAccount('은행', AccountType::ASSET, 'BANK', CashFlowSection::NONE, '500000'),
            'card' => $this->saveAccount('카드', AccountType::LIABILITY, 'CARD', CashFlowSection::NONE, '0'),
            'loan' => $this->saveAccount('대출', AccountType::LIABILITY, 'LOAN', CashFlowSection::NONE, '0'),
            'equity' => $this->saveAccount('자본금', AccountType::EQUITY, null, CashFlowSection::NONE, '600000'),
            'food' => $this->saveAccount('식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING, '0'),
            'salary' => $this->saveAccount('급여', AccountType::INCOME, null, CashFlowSection::OPERATING, '0'),
        ];
    }

    public function test_opening_balance_only_satisfies_identity(): void
    {
        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('600000.00')));
        self::assertTrue($sheet->totalLiabilities()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($sheet->totalEquity()->isEqualTo(BigDecimal::of('600000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_cash_expense_reduces_assets_increases_expense_net_income_down(): void
    {
        // Dr 식비 10000 / Cr 현금 10000 — cash -10000, expense +10000
        $this->recordEntry('2026-04-20', '10000', $this->ids['food'], $this->ids['cash'], debit: 'food');

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('590000.00')));
        self::assertTrue($sheet->netIncome()->isEqualTo(BigDecimal::of('-10000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_card_expense_increases_liabilities(): void
    {
        // Dr 식비 30000 / Cr 카드 30000
        $this->recordEntry('2026-04-20', '30000', $this->ids['food'], $this->ids['card'], debit: 'food');

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('600000.00')));
        self::assertTrue($sheet->totalLiabilities()->isEqualTo(BigDecimal::of('30000.00')));
        self::assertTrue($sheet->netIncome()->isEqualTo(BigDecimal::of('-30000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_salary_income_increases_cash_and_net_income(): void
    {
        // Dr 은행 3,000,000 / Cr 급여 3,000,000
        $this->recordEntry('2026-04-20', '3000000', $this->ids['bank'], $this->ids['salary'], debit: 'bank');

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('3600000.00')));
        self::assertTrue($sheet->netIncome()->isEqualTo(BigDecimal::of('3000000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_excludes_entries_after_as_of_date(): void
    {
        $this->recordEntry('2026-04-20', '10000', $this->ids['food'], $this->ids['cash'], debit: 'food');
        $this->recordEntry('2026-04-25', '50000', $this->ids['food'], $this->ids['cash'], debit: 'food');

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('590000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_excludes_soft_deleted_entries(): void
    {
        $entry = $this->recordEntry('2026-04-20', '10000', $this->ids['food'], $this->ids['cash'], debit: 'food');
        $this->entries->softDelete((int) $entry->id(), self::USER);

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        self::assertTrue($sheet->totalAssets()->isEqualTo(BigDecimal::of('600000.00')));
        self::assertTrue($sheet->isBalanced());
    }

    public function test_per_account_balances_exposed(): void
    {
        $this->recordEntry('2026-04-20', '30000', $this->ids['food'], $this->ids['card'], debit: 'food');

        $sheet = $this->service->compute(self::USER, new DateTimeImmutable('2026-04-22'));

        $assetCash = $sheet->assetLineByAccountId($this->ids['cash']);
        self::assertNotNull($assetCash);
        self::assertTrue($assetCash->balance->isEqualTo(BigDecimal::of('100000.00')));

        $liabCard = $sheet->liabilityLineByAccountId($this->ids['card']);
        self::assertNotNull($liabCard);
        self::assertTrue($liabCard->balance->isEqualTo(BigDecimal::of('30000.00')));
    }

    private function saveAccount(
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
        $this->accounts->save($account);
        return (int) $account->id();
    }

    private function recordEntry(
        string $date,
        string $amount,
        int $debitAccount,
        int $creditAccount,
        string $debit,
    ): JournalEntry {
        $entry = JournalEntry::record(
            userId: self::USER,
            occurredOn: new DateTimeImmutable($date),
            memo: null,
            merchant: null,
            paymentMethod: PaymentMethod::CASH,
            lines: [
                JournalEntryLine::debit($debitAccount, BigDecimal::of($amount)),
                JournalEntryLine::credit($creditAccount, BigDecimal::of($amount)),
            ],
        );
        $this->entries->save($entry);
        return $entry;
    }
}
