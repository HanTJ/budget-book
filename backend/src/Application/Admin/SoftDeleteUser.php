<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Domain\Account\UserRepository;

final class SoftDeleteUser
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function handle(int $userId): void
    {
        if ($this->users->findById($userId) === null) {
            throw UserNotFound::forId($userId);
        }
        $this->users->softDelete($userId);
    }
}
