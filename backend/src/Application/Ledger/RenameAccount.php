<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;

final class RenameAccount
{
    public function __construct(private readonly AccountRepository $accounts)
    {
    }

    public function handle(int $userId, int $accountId, string $newName): Account
    {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->userId !== $userId) {
            throw AccountNotFound::forId($accountId);
        }
        $account->rename($newName);
        $this->accounts->save($account);
        return $account;
    }
}
