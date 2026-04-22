<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Ledger;

use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeedDefaultAccounts::class)]
final class SeedDefaultAccountsTest extends TestCase
{
    private InMemoryAccountRepository $repo;
    private SeedDefaultAccounts $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new InMemoryAccountRepository();
        $this->service = new SeedDefaultAccounts($this->repo);
    }

    public function test_seeds_full_default_set_for_user(): void
    {
        $this->service->seed(userId: 7);

        $accounts = $this->repo->listForUser(7);
        self::assertNotEmpty($accounts);

        $byType = [];
        foreach ($accounts as $account) {
            $byType[$account->type->value] = ($byType[$account->type->value] ?? 0) + 1;
            self::assertTrue($account->isSystem, 'Seeded account must be flagged is_system.');
        }

        self::assertGreaterThanOrEqual(1, $byType['ASSET'] ?? 0);
        self::assertGreaterThanOrEqual(1, $byType['LIABILITY'] ?? 0);
        self::assertGreaterThanOrEqual(1, $byType['EQUITY'] ?? 0);
        self::assertGreaterThanOrEqual(3, $byType['INCOME'] ?? 0);
        self::assertGreaterThanOrEqual(5, $byType['EXPENSE'] ?? 0);
    }

    public function test_seeded_income_accounts_carry_cash_flow_section(): void
    {
        $this->service->seed(userId: 1);

        $incomes = array_filter(
            $this->repo->listForUser(1),
            static fn ($account) => $account->type === AccountType::INCOME,
        );

        self::assertNotEmpty($incomes);
        foreach ($incomes as $account) {
            self::assertNotSame(CashFlowSection::NONE, $account->cashFlowSection);
        }
    }

    public function test_seeded_expense_accounts_carry_cash_flow_section(): void
    {
        $this->service->seed(userId: 1);

        $expenses = array_filter(
            $this->repo->listForUser(1),
            static fn ($account) => $account->type === AccountType::EXPENSE,
        );

        self::assertNotEmpty($expenses);
        foreach ($expenses as $account) {
            self::assertNotSame(CashFlowSection::NONE, $account->cashFlowSection);
        }
    }

    public function test_idempotent_when_called_twice(): void
    {
        $this->service->seed(userId: 1);
        $firstCount = count($this->repo->listForUser(1));

        $this->service->seed(userId: 1);
        $secondCount = count($this->repo->listForUser(1));

        self::assertSame($firstCount, $secondCount);
    }

    public function test_seeds_are_user_scoped(): void
    {
        $this->service->seed(userId: 1);
        $this->service->seed(userId: 2);

        $user1 = $this->repo->listForUser(1);
        $user2 = $this->repo->listForUser(2);

        foreach ($user1 as $account) {
            self::assertSame(1, $account->userId);
        }
        foreach ($user2 as $account) {
            self::assertSame(2, $account->userId);
        }
    }
}
