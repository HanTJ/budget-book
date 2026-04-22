<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Admin;

use BudgetBook\Application\Admin\SoftDeleteUser;
use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoftDeleteUser::class)]
final class SoftDeleteUserTest extends TestCase
{
    public function test_soft_delete_hides_user_from_future_lookups(): void
    {
        $users = new InMemoryUserRepository();
        $user = User::register(
            email: Email::of('delete-me@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '삭제 대상',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $users->save($user);

        $useCase = new SoftDeleteUser($users);
        $useCase->handle((int) $user->id());

        self::assertNull($users->findById((int) $user->id()));
    }

    public function test_missing_user_throws(): void
    {
        $users = new InMemoryUserRepository();
        $this->expectException(UserNotFound::class);
        (new SoftDeleteUser($users))->handle(999);
    }
}
