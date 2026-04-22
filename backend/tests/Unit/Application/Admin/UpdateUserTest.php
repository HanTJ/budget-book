<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Admin;

use BudgetBook\Application\Admin\UpdateUser;
use BudgetBook\Application\Admin\UpdateUserInput;
use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateUser::class)]
final class UpdateUserTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryAccountRepository $accounts;
    private UpdateUser $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->useCase = new UpdateUser(
            $this->users,
            new SeedDefaultAccounts($this->accounts),
        );
    }

    public function test_approving_pending_user_activates_and_seeds_accounts(): void
    {
        $user = $this->pendingUser();

        $updated = $this->useCase->handle(new UpdateUserInput(
            userId: (int) $user->id(),
            status: UserStatus::ACTIVE,
            role: null,
        ));

        self::assertSame(UserStatus::ACTIVE, $updated->status);
        self::assertNotEmpty($this->accounts->listForUser((int) $user->id()));
    }

    public function test_suspending_active_user_does_not_reseed(): void
    {
        $user = $this->pendingUser();
        $this->useCase->handle(new UpdateUserInput(
            userId: (int) $user->id(),
            status: UserStatus::ACTIVE,
            role: null,
        ));
        $seededCount = count($this->accounts->listForUser((int) $user->id()));

        $this->useCase->handle(new UpdateUserInput(
            userId: (int) $user->id(),
            status: UserStatus::SUSPENDED,
            role: null,
        ));

        self::assertSame(UserStatus::SUSPENDED, $user->status);
        self::assertSame($seededCount, count($this->accounts->listForUser((int) $user->id())));
    }

    public function test_role_change_to_admin(): void
    {
        $user = $this->pendingUser();

        $updated = $this->useCase->handle(new UpdateUserInput(
            userId: (int) $user->id(),
            status: null,
            role: UserRole::ADMIN,
        ));

        self::assertSame(UserRole::ADMIN, $updated->role);
    }

    public function test_missing_user_throws(): void
    {
        $this->expectException(UserNotFound::class);
        $this->useCase->handle(new UpdateUserInput(userId: 999, status: null, role: null));
    }

    private function pendingUser(): User
    {
        $user = User::register(
            email: Email::of('pending@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '대기',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $this->users->save($user);
        return $user;
    }
}
