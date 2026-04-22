<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Persistence;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentAccountRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EloquentAccountRepository::class)]
final class EloquentAccountRepositoryTest extends DatabaseTestCase
{
    private EloquentAccountRepository $accounts;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new EloquentAccountRepository();
        $this->userId = $this->seedUser();
    }

    public function test_save_persists_new_account_with_id(): void
    {
        $account = Account::create(
            userId: $this->userId,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );

        $this->accounts->save($account);

        self::assertNotNull($account->id());
        $found = $this->accounts->findById((int) $account->id());
        self::assertNotNull($found);
        self::assertSame('현금', $found->name);
        self::assertTrue($found->openingBalance->isEqualTo(BigDecimal::zero()));
    }

    public function test_list_for_user_returns_only_their_accounts(): void
    {
        $otherUser = $this->seedUser('other@example.com');

        $this->accounts->save(Account::create(
            userId: $this->userId,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        ));
        $this->accounts->save(Account::create(
            userId: $otherUser,
            name: '식비',
            type: AccountType::EXPENSE,
            subtype: null,
            section: CashFlowSection::OPERATING,
        ));

        $mine = $this->accounts->listForUser($this->userId);
        self::assertCount(1, $mine);
        self::assertSame('현금', $mine[0]->name);
    }

    public function test_soft_delete_hides_account_from_list_and_find(): void
    {
        $account = Account::create(
            userId: $this->userId,
            name: '폐기 예정',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );
        $this->accounts->save($account);
        $id = (int) $account->id();

        $this->accounts->softDelete($id);

        self::assertNull($this->accounts->findById($id));
        self::assertEmpty($this->accounts->listForUser($this->userId));
    }

    public function test_save_updates_existing_account(): void
    {
        $account = Account::create(
            userId: $this->userId,
            name: '예전 이름',
            type: AccountType::ASSET,
            subtype: 'BANK',
            section: CashFlowSection::NONE,
        );
        $this->accounts->save($account);

        $account->rename('새 이름');
        $this->accounts->save($account);

        $found = $this->accounts->findById((int) $account->id());
        self::assertNotNull($found);
        self::assertSame('새 이름', $found->name);
    }

    private function seedUser(string $email = 'owner@example.com'): int
    {
        $users = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '소유자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        $users->save($user);
        return (int) $user->id();
    }
}
