<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Auth;

use BudgetBook\Application\Auth\LoginUser;
use BudgetBook\Application\Auth\LoginUserInput;
use BudgetBook\Application\Exception\AccountPending;
use BudgetBook\Application\Exception\AccountSuspended;
use BudgetBook\Application\Exception\InvalidCredentials;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Security\FakeTokenService;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginUser::class)]
final class LoginUserTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryUserRepository $repo;
    private LoginUser $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00'));
        $this->repo = new InMemoryUserRepository();
        $this->useCase = new LoginUser($this->repo, new FakeTokenService());
    }

    public function test_active_user_receives_access_and_refresh_tokens(): void
    {
        $user = $this->saveUser(activate: true);

        $output = $this->useCase->handle(new LoginUserInput(
            email: 'login@example.com',
            plainPassword: 'correct-horse-battery',
        ));

        self::assertSame($user->id(), $output->userId);
        self::assertStringStartsWith('access-', $output->accessToken);
        self::assertStringStartsWith('refresh-', $output->refreshToken);
    }

    public function test_pending_user_rejected_with_account_pending(): void
    {
        $this->saveUser(activate: false);

        $this->expectException(AccountPending::class);
        $this->useCase->handle(new LoginUserInput(
            email: 'login@example.com',
            plainPassword: 'correct-horse-battery',
        ));
    }

    public function test_suspended_user_rejected_with_account_suspended(): void
    {
        $user = $this->saveUser(activate: true);
        $user->suspend();
        $this->repo->save($user);

        $this->expectException(AccountSuspended::class);
        $this->useCase->handle(new LoginUserInput(
            email: 'login@example.com',
            plainPassword: 'correct-horse-battery',
        ));
    }

    public function test_unknown_email_rejected_with_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentials::class);
        $this->useCase->handle(new LoginUserInput(
            email: 'missing@example.com',
            plainPassword: 'correct-horse-battery',
        ));
    }

    public function test_wrong_password_rejected_with_invalid_credentials(): void
    {
        $this->saveUser(activate: true);

        $this->expectException(InvalidCredentials::class);
        $this->useCase->handle(new LoginUserInput(
            email: 'login@example.com',
            plainPassword: 'wrong-password',
        ));
    }

    private function saveUser(bool $activate): User
    {
        $user = User::register(
            email: Email::of('login@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '로그인',
            clock: $this->clock,
        );
        if ($activate) {
            $user->activate();
        }
        $this->repo->save($user);
        return $user;
    }
}
