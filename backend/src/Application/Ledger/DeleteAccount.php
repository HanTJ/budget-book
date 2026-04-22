<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Domain\Ledger\AccountRepository;

final class DeleteAccount
{
    public function __construct(private readonly AccountRepository $accounts)
    {
    }

    public function handle(int $userId, int $accountId): void
    {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->userId !== $userId) {
            throw AccountNotFound::forId($accountId);
        }
        $this->accounts->softDelete($accountId);
    }
}
