<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Ledger;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Domain\Ledger\NormalBalance;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
final class AccountTest extends TestCase
{
    public function test_create_assigns_normal_balance_based_on_type_when_omitted(): void
    {
        $account = Account::create(
            userId: 1,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );

        self::assertSame(NormalBalance::DEBIT, $account->normalBalance);
        self::assertTrue($account->openingBalance->isEqualTo(BigDecimal::zero()));
        self::assertFalse($account->isSystem);
    }

    #[DataProvider('typeToNormalBalance')]
    public function test_normal_balance_matches_type(
        AccountType $type,
        CashFlowSection $section,
        NormalBalance $expected,
    ): void {
        $account = Account::create(
            userId: 1,
            name: '테스트',
            type: $type,
            subtype: null,
            section: $section,
        );

        self::assertSame($expected, $account->normalBalance);
    }

    /**
     * @return iterable<string, array{AccountType, CashFlowSection, NormalBalance}>
     */
    public static function typeToNormalBalance(): iterable
    {
        yield 'ASSET -> DEBIT' => [AccountType::ASSET, CashFlowSection::NONE, NormalBalance::DEBIT];
        yield 'EXPENSE -> DEBIT' => [AccountType::EXPENSE, CashFlowSection::OPERATING, NormalBalance::DEBIT];
        yield 'LIABILITY -> CREDIT' => [AccountType::LIABILITY, CashFlowSection::NONE, NormalBalance::CREDIT];
        yield 'EQUITY -> CREDIT' => [AccountType::EQUITY, CashFlowSection::NONE, NormalBalance::CREDIT];
        yield 'INCOME -> CREDIT' => [AccountType::INCOME, CashFlowSection::OPERATING, NormalBalance::CREDIT];
    }

    public function test_income_account_requires_cash_flow_section(): void
    {
        $this->expectException(DomainException::class);
        Account::create(
            userId: 1,
            name: '급여',
            type: AccountType::INCOME,
            subtype: null,
            section: CashFlowSection::NONE,
        );
    }

    public function test_expense_account_requires_cash_flow_section(): void
    {
        $this->expectException(DomainException::class);
        Account::create(
            userId: 1,
            name: '식비',
            type: AccountType::EXPENSE,
            subtype: null,
            section: CashFlowSection::NONE,
        );
    }

    public function test_name_cannot_be_blank(): void
    {
        $this->expectException(DomainException::class);
        Account::create(
            userId: 1,
            name: '   ',
            type: AccountType::ASSET,
            subtype: null,
            section: CashFlowSection::NONE,
        );
    }

    public function test_opening_balance_cannot_be_negative(): void
    {
        $this->expectException(DomainException::class);
        Account::create(
            userId: 1,
            name: '현금',
            type: AccountType::ASSET,
            subtype: null,
            section: CashFlowSection::NONE,
            openingBalance: BigDecimal::of('-1'),
        );
    }

    public function test_rename_updates_name(): void
    {
        $account = Account::create(
            userId: 1,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );

        $account->rename('주머니 현금');

        self::assertSame('주머니 현금', $account->name);
    }

    public function test_markAsSystem_flags_as_is_system(): void
    {
        $account = Account::create(
            userId: 1,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );

        $system = $account->markAsSystem();

        self::assertTrue($system->isSystem);
    }
}
