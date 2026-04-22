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
use BudgetBook\Domain\Reporting\CashFlowStatement;
use BudgetBook\Domain\Reporting\CashFlowStatementService;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryJournalEntryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CashFlowStatementService::class)]
#[CoversClass(CashFlowStatement::class)]
final class CashFlowStatementServiceTest extends TestCase
{
    private const USER = 1;

    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $entries;
    private CashFlowStatementService $service;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new InMemoryAccountRepository();
        $this->entries = new InMemoryJournalEntryRepository();
        $this->service = new CashFlowStatementService($this->accounts, $this->entries);

        $this->ids = [
            'cash' => $this->saveAccount('현금', AccountType::ASSET, 'CASH', CashFlowSection::NONE, '100000'),
            'bank' => $this->saveAccount('은행', AccountType::ASSET, 'BANK', CashFlowSection::NONE, '500000'),
            'card' => $this->saveAccount('카드', AccountType::LIABILITY, 'CARD', CashFlowSection::NONE, '0'),
            'loan' => $this->saveAccount('대출', AccountType::LIABILITY, 'LOAN', CashFlowSection::NONE, '0'),
            'food' => $this->saveAccount('식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING, '0'),
            'stock' => $this->saveAccount('주식매수', AccountType::EXPENSE, null, CashFlowSection::INVESTING, '0'),
            'interest' => $this->saveAccount('대출이자', AccountType::EXPENSE, null, CashFlowSection::FINANCING, '0'),
            'salary' => $this->saveAccount('급여', AccountType::INCOME, null, CashFlowSection::OPERATING, '0'),
            'loanIn' => $this->saveAccount('대출수령', AccountType::INCOME, null, CashFlowSection::FINANCING, '0'),
        ];
    }

    public function test_cash_expense_counts_as_operating_outflow(): void
    {
        // Dr 식비 10000 / Cr 현금 10000
        $this->recordEntry('2026-04-10', $this->ids['food'], $this->ids['cash'], '10000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->operatingOutflow()->isEqualTo(BigDecimal::of('10000.00')));
        self::assertTrue($stmt->operatingInflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_salary_into_bank_counts_as_operating_inflow(): void
    {
        // Dr 은행 3000000 / Cr 급여 3000000
        $this->recordEntry('2026-04-10', $this->ids['bank'], $this->ids['salary'], '3000000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->operatingInflow()->isEqualTo(BigDecimal::of('3000000.00')));
        self::assertTrue($stmt->operatingOutflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_card_purchase_does_not_affect_cash_flow(): void
    {
        // Dr 식비 30000 / Cr 카드 30000 — no cash movement
        $this->recordEntry('2026-04-10', $this->ids['food'], $this->ids['card'], '30000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->operatingInflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->operatingOutflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->investingInflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->financingInflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_investing_outflow_classification(): void
    {
        // 주식매수 via cash
        $this->recordEntry('2026-04-10', $this->ids['stock'], $this->ids['cash'], '500000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->investingOutflow()->isEqualTo(BigDecimal::of('500000.00')));
        self::assertTrue($stmt->operatingOutflow()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_financing_inflow_from_loan_receipt(): void
    {
        // Dr 은행 10,000,000 / Cr 대출수령 10,000,000 — 대출 받음
        $this->recordEntry('2026-04-10', $this->ids['bank'], $this->ids['loanIn'], '10000000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->financingInflow()->isEqualTo(BigDecimal::of('10000000.00')));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_financing_outflow_from_interest_payment(): void
    {
        // 대출이자 납부 via 현금
        $this->recordEntry('2026-04-10', $this->ids['interest'], $this->ids['cash'], '50000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->financingOutflow()->isEqualTo(BigDecimal::of('50000.00')));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_cash_between_cash_accounts_does_not_produce_section_totals(): void
    {
        // Dr 현금 50000 / Cr 은행 50000 — 현금 인출
        $this->recordEntry('2026-04-10', $this->ids['cash'], $this->ids['bank'], '50000');

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->operatingNet()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->investingNet()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->financingNet()->isEqualTo(BigDecimal::zero()));
        self::assertTrue($stmt->isReconciled());
    }

    public function test_mixed_period_reconciles_to_cash_delta(): void
    {
        $this->recordEntry('2026-04-10', $this->ids['bank'], $this->ids['salary'], '3000000');  // +3M inflow
        $this->recordEntry('2026-04-11', $this->ids['food'], $this->ids['cash'], '15000');       // -15000 op
        $this->recordEntry('2026-04-12', $this->ids['stock'], $this->ids['bank'], '500000');     // -500000 investing
        $this->recordEntry('2026-04-13', $this->ids['food'], $this->ids['card'], '20000');       // card, no cash delta

        $stmt = $this->service->compute(
            self::USER,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );

        self::assertTrue($stmt->operatingNet()->isEqualTo(BigDecimal::of('2985000.00')));
        self::assertTrue($stmt->investingNet()->isEqualTo(BigDecimal::of('-500000.00')));
        self::assertTrue($stmt->isReconciled());
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

    private function recordEntry(string $date, int $debitId, int $creditId, string $amount): JournalEntry
    {
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
        $this->entries->save($entry);
        return $entry;
    }
}
