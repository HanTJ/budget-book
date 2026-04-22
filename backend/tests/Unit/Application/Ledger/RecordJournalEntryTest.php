<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Ledger;

use Brick\Math\BigDecimal;
use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Application\Exception\InvalidJournalEntry;
use BudgetBook\Application\Ledger\RecordJournalEntry;
use BudgetBook\Application\Ledger\RecordJournalEntryInput;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Domain\Ledger\PaymentMethod;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryJournalEntryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordJournalEntry::class)]
final class RecordJournalEntryTest extends TestCase
{
    private const USER_ID = 7;

    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $entries;
    private RecordJournalEntry $useCase;

    /** @var array<string, int> */
    private array $accountIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new InMemoryAccountRepository();
        $this->entries = new InMemoryJournalEntryRepository();
        $this->useCase = new RecordJournalEntry($this->accounts, $this->entries);

        $this->accountIds = [];
        $this->accountIds['cash'] = $this->seedAccount('현금', AccountType::ASSET, 'CASH', CashFlowSection::NONE);
        $this->accountIds['bank'] = $this->seedAccount('은행', AccountType::ASSET, 'BANK', CashFlowSection::NONE);
        $this->accountIds['card'] = $this->seedAccount('카드', AccountType::LIABILITY, 'CARD', CashFlowSection::NONE);
        $this->accountIds['food'] = $this->seedAccount('식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING);
        $this->accountIds['salary'] = $this->seedAccount('급여', AccountType::INCOME, null, CashFlowSection::OPERATING);
    }

    public function test_cash_expense_debits_category_and_credits_user_cash(): void
    {
        $entry = $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '12000',
            paymentMethod: PaymentMethod::CASH,
            categoryAccountId: $this->accountIds['food'],
            counterAccountId: null,
            merchant: '분식집',
            memo: null,
        ));

        self::assertCount(2, $entry->lines);
        [$debit, $credit] = $this->splitByDirection($entry);
        self::assertSame($this->accountIds['food'], $debit->accountId);
        self::assertSame($this->accountIds['cash'], $credit->accountId);
        self::assertTrue($debit->debit->isEqualTo(BigDecimal::of('12000')));
    }

    public function test_card_expense_debits_category_and_credits_card_liability(): void
    {
        $entry = $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '30000',
            paymentMethod: PaymentMethod::CARD,
            categoryAccountId: $this->accountIds['food'],
            counterAccountId: null,
            merchant: '마트',
            memo: null,
        ));

        [$debit, $credit] = $this->splitByDirection($entry);
        self::assertSame($this->accountIds['food'], $debit->accountId);
        self::assertSame($this->accountIds['card'], $credit->accountId);
    }

    public function test_transfer_expense_requires_counter_account(): void
    {
        $this->expectException(InvalidJournalEntry::class);
        $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '70000',
            paymentMethod: PaymentMethod::TRANSFER,
            categoryAccountId: $this->accountIds['food'],
            counterAccountId: null,
            merchant: '공과금',
            memo: null,
        ));
    }

    public function test_transfer_expense_uses_explicit_counter(): void
    {
        $entry = $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '70000',
            paymentMethod: PaymentMethod::TRANSFER,
            categoryAccountId: $this->accountIds['food'],
            counterAccountId: $this->accountIds['bank'],
            merchant: '배달',
            memo: null,
        ));

        [$debit, $credit] = $this->splitByDirection($entry);
        self::assertSame($this->accountIds['food'], $debit->accountId);
        self::assertSame($this->accountIds['bank'], $credit->accountId);
    }

    public function test_income_cash_debits_cash_credits_income(): void
    {
        $entry = $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '100000',
            paymentMethod: PaymentMethod::CASH,
            categoryAccountId: $this->accountIds['salary'],
            counterAccountId: null,
            merchant: null,
            memo: '용돈',
        ));

        [$debit, $credit] = $this->splitByDirection($entry);
        self::assertSame($this->accountIds['cash'], $debit->accountId);
        self::assertSame($this->accountIds['salary'], $credit->accountId);
    }

    public function test_income_transfer_debits_counter_bank_credits_income(): void
    {
        $entry = $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '3000000',
            paymentMethod: PaymentMethod::TRANSFER,
            categoryAccountId: $this->accountIds['salary'],
            counterAccountId: $this->accountIds['bank'],
            merchant: '회사',
            memo: '월급',
        ));

        [$debit, $credit] = $this->splitByDirection($entry);
        self::assertSame($this->accountIds['bank'], $debit->accountId);
        self::assertSame($this->accountIds['salary'], $credit->accountId);
    }

    public function test_income_with_card_is_rejected(): void
    {
        $this->expectException(InvalidJournalEntry::class);
        $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '100',
            paymentMethod: PaymentMethod::CARD,
            categoryAccountId: $this->accountIds['salary'],
            counterAccountId: null,
            merchant: null,
            memo: null,
        ));
    }

    public function test_unknown_category_throws_account_not_found(): void
    {
        $this->expectException(AccountNotFound::class);
        $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '100',
            paymentMethod: PaymentMethod::CASH,
            categoryAccountId: 9999,
            counterAccountId: null,
            merchant: null,
            memo: null,
        ));
    }

    public function test_other_users_account_is_not_accessible(): void
    {
        $foreign = $this->seedAccount('타인식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING, userId: 99);

        $this->expectException(AccountNotFound::class);
        $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '100',
            paymentMethod: PaymentMethod::CASH,
            categoryAccountId: $foreign,
            counterAccountId: null,
            merchant: null,
            memo: null,
        ));
    }

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(InvalidJournalEntry::class);
        $this->useCase->handle(new RecordJournalEntryInput(
            userId: self::USER_ID,
            occurredOn: '2026-04-22',
            amount: '0',
            paymentMethod: PaymentMethod::CASH,
            categoryAccountId: $this->accountIds['food'],
            counterAccountId: null,
            merchant: null,
            memo: null,
        ));
    }

    /**
     * @return array{0:\BudgetBook\Domain\Ledger\JournalEntryLine,1:\BudgetBook\Domain\Ledger\JournalEntryLine}
     */
    private function splitByDirection(\BudgetBook\Domain\Ledger\JournalEntry $entry): array
    {
        $debit = null;
        $credit = null;
        foreach ($entry->lines as $line) {
            if ($line->isDebit()) {
                $debit = $line;
            } else {
                $credit = $line;
            }
        }
        self::assertNotNull($debit);
        self::assertNotNull($credit);
        return [$debit, $credit];
    }

    private function seedAccount(
        string $name,
        AccountType $type,
        ?string $subtype,
        CashFlowSection $section,
        int $userId = self::USER_ID,
    ): int {
        $account = Account::create(
            userId: $userId,
            name: $name,
            type: $type,
            subtype: $subtype,
            section: $section,
        );
        $this->accounts->save($account);
        return (int) $account->id();
    }
}
