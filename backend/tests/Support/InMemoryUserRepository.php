<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $byId = [];

    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email->equals($email)) {
                return $user;
            }
        }
        return null;
    }

    public function emailExists(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function save(User $user): void
    {
        if ($user->id() === null) {
            $id = $this->nextId++;
            $user->assignId($id);
            $this->byId[$id] = $user;
            return;
        }

        $this->byId[$user->id()] = $user;
    }
}
