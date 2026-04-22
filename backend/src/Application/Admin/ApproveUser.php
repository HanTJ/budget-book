<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Account\UserRepository;

final class ApproveUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SeedDefaultAccounts $seed,
    ) {
    }

    public function handle(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw UserNotFound::forId($userId);
        }

        $user->activate();
        $this->users->save($user);

        $this->seed->seed($userId);
    }
}
