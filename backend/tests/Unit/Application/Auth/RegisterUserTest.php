<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Auth;

use BudgetBook\Application\Auth\RegisterUser;
use BudgetBook\Application\Auth\RegisterUserInput;
use BudgetBook\Application\Exception\EmailAlreadyRegistered;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterUser::class)]
final class RegisterUserTest extends TestCase
{
    public function test_registers_new_user_in_pending_state(): void
    {
        $repo = new InMemoryUserRepository();
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00'));
        $useCase = new RegisterUser($repo, $clock);

        $output = $useCase->handle(new RegisterUserInput(
            email: 'new@example.com',
            plainPassword: 'correct-horse-battery',
            displayName: '새 사용자',
        ));

        self::assertGreaterThan(0, $output->userId);
        self::assertSame('new@example.com', $output->email);
        self::assertSame(UserStatus::PENDING, $output->status);

        $persisted = $repo->findByEmail(Email::of('new@example.com'));
        self::assertInstanceOf(User::class, $persisted);
        self::assertTrue($persisted->password->verify('correct-horse-battery'));
    }

    public function test_rejects_duplicate_email(): void
    {
        $repo = new InMemoryUserRepository();
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00'));
        $repo->save(User::register(
            email: Email::of('dup@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '기존',
            clock: $clock,
        ));

        $useCase = new RegisterUser($repo, $clock);

        $this->expectException(EmailAlreadyRegistered::class);
        $useCase->handle(new RegisterUserInput(
            email: 'DUP@example.com',
            plainPassword: 'correct-horse-battery',
            displayName: '중복',
        ));
    }
}
