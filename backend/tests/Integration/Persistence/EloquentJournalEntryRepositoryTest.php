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
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\PaymentMethod;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentAccountRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentJournalEntryRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EloquentJournalEntryRepository::class)]
final class EloquentJournalEntryRepositoryTest extends DatabaseTestCase
{
    private EloquentJournalEntryRepository $entries;
    private int $userId;
    private int $cashAccountId;
    private int $expenseAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entries = new EloquentJournalEntryRepository();
        $this->userId = $this->seedUser();
        $accounts = new EloquentAccountRepository();

        $cash = Account::create(
            userId: $this->userId,
            name: '현금',
            type: AccountType::ASSET,
            subtype: 'CASH',
            section: CashFlowSection::NONE,
        );
        $accounts->save($cash);
        $this->cashAccountId = (int) $cash->id();

        $food = Account::create(
            userId: $this->userId,
            name: '식비',
            type: AccountType::EXPENSE,
            subtype: null,
            section: CashFlowSection::OPERATING,
        );
        $accounts->save($food);
        $this->expenseAccountId = (int) $food->id();
    }

    public function test_save_persists_entry_with_all_lines_atomically(): void
    {
        $entry = $this->buildEntry('10000');

        $this->entries->save($entry);

        self::assertNotNull($entry->id());
        $found = $this->entries->findById((int) $entry->id(), $this->userId);
        self::assertNotNull($found);
        self::assertCount(2, $found->lines);
        self::assertTrue($found->totalDebit()->isEqualTo(BigDecimal::of('10000.00')));
    }

    public function test_list_for_user_returns_entries_in_range_ordered_desc(): void
    {
        $this->entries->save($this->buildEntry('1000', '2026-04-20'));
        $this->entries->save($this->buildEntry('2000', '2026-04-22'));
        $this->entries->save($this->buildEntry('3000', '2026-04-21'));

        $list = $this->entries->listForUser(
            userId: $this->userId,
            from: new DateTimeImmutable('2026-04-20'),
            to: new DateTimeImmutable('2026-04-22'),
        );

        self::assertCount(3, $list);
        self::assertSame('2026-04-22', $list[0]->occurredOn->format('Y-m-d'));
        self::assertSame('2026-04-21', $list[1]->occurredOn->format('Y-m-d'));
    }

    public function test_soft_delete_hides_entry_from_find_and_list(): void
    {
        $entry = $this->buildEntry('5000');
        $this->entries->save($entry);
        $id = (int) $entry->id();

        $this->entries->softDelete($id, $this->userId);

        self::assertNull($this->entries->findById($id, $this->userId));
        $list = $this->entries->listForUser(
            $this->userId,
            new DateTimeImmutable('2026-04-01'),
            new DateTimeImmutable('2026-04-30'),
        );
        self::assertCount(0, $list);
    }

    public function test_soft_delete_ignores_other_user(): void
    {
        $entry = $this->buildEntry('7000');
        $this->entries->save($entry);
        $otherUserId = $this->seedUser('other@example.com');

        $this->entries->softDelete((int) $entry->id(), $otherUserId);

        $found = $this->entries->findById((int) $entry->id(), $this->userId);
        self::assertNotNull($found);
    }

    private function buildEntry(string $amount, string $date = '2026-04-22'): JournalEntry
    {
        return JournalEntry::record(
            userId: $this->userId,
            occurredOn: new DateTimeImmutable($date),
            memo: null,
            merchant: '테스트',
            paymentMethod: PaymentMethod::CASH,
            lines: [
                JournalEntryLine::debit($this->expenseAccountId, BigDecimal::of($amount)),
                JournalEntryLine::credit($this->cashAccountId, BigDecimal::of($amount)),
            ],
        );
    }

    private function seedUser(string $email = 'ledger-owner@example.com'): int
    {
        $users = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '원장 소유자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        $users->save($user);
        return (int) $user->id();
    }
}
