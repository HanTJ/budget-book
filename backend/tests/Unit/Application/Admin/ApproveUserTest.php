<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Admin;

use BudgetBook\Application\Admin\ApproveUser;
use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApproveUser::class)]
final class ApproveUserTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryAccountRepository $accounts;
    private ApproveUser $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->useCase = new ApproveUser(
            $this->users,
            new SeedDefaultAccounts($this->accounts),
        );
    }

    public function test_pending_user_is_activated_and_receives_default_accounts(): void
    {
        $user = $this->pendingUser();

        $this->useCase->handle((int) $user->id());

        self::assertSame(UserStatus::ACTIVE, $user->status);
        self::assertNotEmpty($this->accounts->listForUser((int) $user->id()));
    }

    public function test_approving_already_active_user_does_not_reseed(): void
    {
        $user = $this->pendingUser();
        $this->useCase->handle((int) $user->id());
        $firstCount = count($this->accounts->listForUser((int) $user->id()));

        $this->useCase->handle((int) $user->id());
        $secondCount = count($this->accounts->listForUser((int) $user->id()));

        self::assertSame($firstCount, $secondCount);
    }

    public function test_missing_user_throws_user_not_found(): void
    {
        $this->expectException(UserNotFound::class);
        $this->useCase->handle(999);
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
