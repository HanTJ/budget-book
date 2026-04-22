<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Persistence;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EloquentUserRepository::class)]
final class EloquentUserRepositoryTest extends DatabaseTestCase
{
    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    public function test_save_persists_new_user_with_assigned_id(): void
    {
        $user = $this->makeUser('new@example.com');

        $this->repository->save($user);

        self::assertNotNull($user->id());
        $found = $this->repository->findByEmail(Email::of('new@example.com'));
        self::assertNotNull($found);
        self::assertSame($user->id(), $found->id());
        self::assertSame(UserStatus::PENDING, $found->status);
    }

    public function test_find_by_email_returns_null_when_missing(): void
    {
        self::assertNull($this->repository->findByEmail(Email::of('missing@example.com')));
    }

    public function test_find_by_email_ignores_soft_deleted_rows(): void
    {
        $user = $this->makeUser('deleted@example.com');
        $this->repository->save($user);

        $this->db->getConnection()
            ->table('users')
            ->where('id', $user->id())
            ->update(['deleted_at' => '2026-04-21 00:00:00']);

        self::assertNull($this->repository->findByEmail(Email::of('deleted@example.com')));
    }

    public function test_email_exists_detects_duplicate(): void
    {
        $this->repository->save($this->makeUser('dup@example.com'));

        self::assertTrue($this->repository->emailExists(Email::of('dup@example.com')));
        self::assertFalse($this->repository->emailExists(Email::of('other@example.com')));
    }

    public function test_save_updates_existing_user(): void
    {
        $user = $this->makeUser('update@example.com');
        $this->repository->save($user);

        $user->activate();
        $this->repository->save($user);

        $found = $this->repository->findByEmail(Email::of('update@example.com'));
        self::assertNotNull($found);
        self::assertSame(UserStatus::ACTIVE, $found->status);
    }

    private function makeUser(string $email): User
    {
        return User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '테스트 사용자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00')),
        );
    }
}
