<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Account\UserStatus;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $byId = [];
    /** @var array<int, true> */
    private array $deleted = [];

    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        if (isset($this->deleted[$id])) {
            return null;
        }
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->byId as $id => $user) {
            if (isset($this->deleted[$id])) {
                continue;
            }
            if ($user->email->equals($email)) {
                return $user;
            }
        }
        return null;
    }

    public function listAll(?UserStatus $status): array
    {
        $out = [];
        foreach ($this->byId as $id => $user) {
            if (isset($this->deleted[$id])) {
                continue;
            }
            if ($status !== null && $user->status !== $status) {
                continue;
            }
            $out[] = $user;
        }
        return $out;
    }

    public function softDelete(int $id): void
    {
        if (!isset($this->byId[$id])) {
            return;
        }
        $this->deleted[$id] = true;
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
