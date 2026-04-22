<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;

final class InMemoryAccountRepository implements AccountRepository
{
    /** @var array<int, Account> */
    private array $byId = [];
    /** @var array<int, true> */
    private array $deletedIds = [];
    private int $nextId = 1;

    public function findById(int $id): ?Account
    {
        if (isset($this->deletedIds[$id])) {
            return null;
        }
        return $this->byId[$id] ?? null;
    }

    public function listForUser(int $userId): array
    {
        $out = [];
        foreach ($this->byId as $id => $account) {
            if (isset($this->deletedIds[$id])) {
                continue;
            }
            if ($account->userId === $userId) {
                $out[] = $account;
            }
        }
        return $out;
    }

    public function save(Account $account): void
    {
        if ($account->id() === null) {
            $id = $this->nextId++;
            $account->assignId($id);
            $this->byId[$id] = $account;
            return;
        }
        $this->byId[$account->id()] = $account;
    }

    public function softDelete(int $id): void
    {
        $this->deletedIds[$id] = true;
    }
}
