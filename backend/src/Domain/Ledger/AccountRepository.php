<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

interface AccountRepository
{
    public function findById(int $id): ?Account;

    /**
     * @return list<Account>
     */
    public function listForUser(int $userId): array;

    public function save(Account $account): void;

    public function softDelete(int $id): void;
}
