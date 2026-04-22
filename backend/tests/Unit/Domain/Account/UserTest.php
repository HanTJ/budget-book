<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Account;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function test_register_creates_pending_user_with_role_user(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00'));

        $user = User::register(
            email: Email::of('new@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '신규 사용자',
            clock: $clock,
        );

        self::assertSame('new@example.com', $user->email->value);
        self::assertSame('신규 사용자', $user->displayName);
        self::assertSame(UserRole::USER, $user->role);
        self::assertSame(UserStatus::PENDING, $user->status);
        self::assertNull($user->id());
        self::assertEquals($clock->now(), $user->createdAt);
    }

    public function test_activate_transitions_pending_to_active(): void
    {
        $user = $this->pendingUser();

        $user->activate();

        self::assertSame(UserStatus::ACTIVE, $user->status);
    }

    public function test_activate_is_idempotent_for_active_user(): void
    {
        $user = $this->pendingUser();
        $user->activate();
        $user->activate();

        self::assertSame(UserStatus::ACTIVE, $user->status);
    }

    public function test_suspend_rejects_when_already_suspended(): void
    {
        $user = $this->pendingUser();
        $user->activate();
        $user->suspend();

        $this->expectException(DomainException::class);
        $user->suspend();
    }

    public function test_display_name_cannot_be_blank(): void
    {
        $this->expectException(DomainException::class);

        User::register(
            email: Email::of('a@b.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '   ',
            clock: new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00')),
        );
    }

    private function pendingUser(): User
    {
        return User::register(
            email: Email::of('a@b.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '테스트',
            clock: new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00')),
        );
    }
}
