<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Application\Admin;

use BudgetBook\Application\Admin\ListUsers;
use BudgetBook\Application\Admin\ListUsersInput;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListUsers::class)]
final class ListUsersTest extends TestCase
{
    private InMemoryUserRepository $repo;
    private FixedClock $clock;
    private ListUsers $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new InMemoryUserRepository();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00'));
        $this->useCase = new ListUsers($this->repo);
    }

    public function test_returns_all_users_with_default_input(): void
    {
        $this->seed('a@example.com', activate: false);
        $this->seed('b@example.com', activate: true);

        $list = $this->useCase->handle(new ListUsersInput(status: null));

        self::assertCount(2, $list);
    }

    public function test_filters_by_status(): void
    {
        $this->seed('pending@example.com', activate: false);
        $this->seed('active@example.com', activate: true);

        $only = $this->useCase->handle(new ListUsersInput(status: UserStatus::PENDING));

        self::assertCount(1, $only);
        self::assertSame('pending@example.com', $only[0]->email->value);
    }

    private function seed(string $email, bool $activate): User
    {
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: $email,
            clock: $this->clock,
        );
        if ($activate) {
            $user->activate();
        }
        $this->repo->save($user);
        return $user;
    }
}
